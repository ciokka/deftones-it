<?php
/**
 * migliora.php — rilegge gli articoli e propone una versione migliore.
 *
 *   php migliora.php          lavora le revisioni in attesa
 *   php migliora.php --una    ne lavora una sola, per provare
 *
 * Le revisioni si chiedono dal pannello, dal pulsante «migliora» sotto
 * un articolo. Da qui non se ne inventano: questo programma svuota una
 * coda, non decide cosa rileggere. È voluto — una rilettura costa, e
 * chi la paga deve averla chiesta.
 *
 * Due chiamate per revisione, come per le richieste. Nella prima il
 * modello CERCA e riferisce; nella seconda riscrive avendo davanti solo
 * ciò che ha letto. Un articolo «migliorato» senza andare a vedere
 * diventa più lungo, non più informato, ed è il modo più efficace di
 * peggiorare un archivio.
 *
 * NIENTE di quello che esce da qui tocca l'articolo. La proposta si
 * posa in df_revisioni e aspetta un clic. È l'unica forma onesta per
 * un'IA che riscrive qualcosa di già pubblicato.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/claude.php';
require __DIR__ . '/../lib/prompts.php';

@set_time_limit(0);
$unaSola = in_array('--una', $argv ?? [], true);
$avvio = microtime(true);

$lock = prendiLock('migliora');
if ($lock === false) { logline('Un altro giro è in corso — esco.', 'migliora'); exit(0); }
function mlog(string $m): void { logline($m, 'migliora'); }

$pdo = db();

// --- revisioni rimaste appese ----------------------------------------
// Stessa rete di scrivi-richieste, stessa ragione: lo stato passa a
// 'lavorazione' quando si comincia, e se il processo muore nel mezzo
// nessun giro successivo la riprenderebbe, perché cercano 'attesa'.
$appese = $pdo->exec('UPDATE ' . t('revisioni') . "
    SET stato = 'attesa',
        nota = CONCAT('ripresa dopo un blocco del ', DATE_FORMAT(NOW(), '%d/%m %H:%i'))
  WHERE stato = 'lavorazione'
    AND creato_il < NOW() - INTERVAL 30 MINUTE");
if ($appese) { mlog(sprintf('  %d revisioni rimaste appese, rimesse in coda', $appese)); }

$attesa = $pdo->query('SELECT r.*, a.titolo_it AS a_titolo, a.sommario_it AS a_sommario,
                              a.corpo_it AS a_corpo, a.tag AS a_tag, a.categoria,
                              a.attendibilita, a.fonte_nome, a.fonte_url,
                              a.pubblicato_il, a.stato AS a_stato
                         FROM ' . t('revisioni') . ' r
                         JOIN ' . t('articles') . " a ON a.id = r.articolo_id
                        WHERE r.stato = 'attesa'
                        ORDER BY r.creato_il LIMIT " . ($unaSola ? 1 : 5))->fetchAll();

if (!$attesa) {
    mlog('Nessuna revisione in attesa.');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}
mlog(sprintf('%d revisioni da lavorare', count($attesa)));

// ---------------------------------------------------------------- giro
// Come in scrivi-richieste: le query preparate NON si creano qui.
// Appartengono alla connessione, e fra questo punto e il loro uso ci
// sono minuti di attesa sull'API.
$fatte = $fallite = 0;
$tokIn = $tokOut = 0;

$preparaSegna = fn(PDO $c) => $c->prepare('UPDATE ' . t('revisioni') . '
      SET stato = ?, titolo_it = ?, sommario_it = ?, corpo_it = ?, tag = ?,
          motivazione = ?, fonti = ?, nota = ?, modello = ?,
          token_in = ?, token_out = ?, elaborato_il = NOW()
    WHERE id = ?');

$inCorso = null;
register_shutdown_function(function () use (&$inCorso) {
    if ($inCorso === null) { return; }
    $e = error_get_last();
    $motivo = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
        ? mb_substr($e['message'], 0, 300)
        : 'il processo è terminato prima di finire';
    try {
        // db(true) e non la connessione di prima: se lo script è morto
        // perché il database era caduto, quella è inservibile.
        db(true)->prepare('UPDATE ' . t('revisioni') . "
                              SET stato = 'errore', nota = ?, elaborato_il = NOW()
                            WHERE id = ? AND stato = 'lavorazione'")
                ->execute([$motivo, $inCorso]);
    } catch (Throwable) {
        // se nemmeno riconnettersi funziona, il recupero degli appesi
        // all'avvio del prossimo giro rimedia comunque
    }
});

foreach ($attesa as $r) {
    $id = (int)$r['id'];
    $inCorso = $id;
    $pdo->prepare('UPDATE ' . t('revisioni') . " SET stato = 'lavorazione' WHERE id = ?")
        ->execute([$id]);
    mlog(sprintf('  [%d] %s', $id, mb_substr((string)$r['a_titolo'], 0, 60)));

    // La fotografia dell'articolo com'è adesso. Si salva PRIMA di
    // partire, non dopo: fra qui e la fine ci sono minuti in cui
    // l'articolo può essere modificato a mano, e il confronto deve
    // mostrare ciò che il modello aveva davvero davanti.
    $pdo->prepare('UPDATE ' . t('revisioni') . '
                      SET prima_titolo = ?, prima_sommario = ?, prima_corpo = ?, prima_tag = ?
                    WHERE id = ?')
        ->execute([$r['a_titolo'], $r['a_sommario'], $r['a_corpo'], $r['a_tag'], $id]);

    $tagOra = json_decode((string)$r['a_tag'], true) ?: [];

    // --- 1. rilettura e ricerca
    $articolo = "TITOLO: {$r['a_titolo']}\n"
              . 'DATA DI PUBBLICAZIONE: ' . ($r['pubblicato_il'] ?: 'sconosciuta') . "\n"
              . "CATEGORIA: {$r['categoria']} · ATTENDIBILITÀ: {$r['attendibilita']}\n"
              . ($r['fonte_nome'] ? "FONTE ORIGINALE: {$r['fonte_nome']} — {$r['fonte_url']}\n" : '')
              . ($tagOra ? 'TAG: ' . implode(', ', $tagOra) . "\n" : '')
              . "\nSOMMARIO\n{$r['a_sommario']}\n"
              . (trim((string)$r['a_corpo']) !== ''
                    ? "\nCORPO\n{$r['a_corpo']}\n"
                    : "\n(l'articolo non ha un corpo esteso: è una notizia breve, "
                      . "tutto sta nel sommario)\n");

    $domanda = "Ecco l'articolo da rileggere.\n\n" . $articolo
             . "\nOggi è il " . date('d/m/Y') . ": tienilo presente quando valuti "
             . "se la vicenda è andata avanti.\n";
    if (trim((string)$r['indicazioni']) !== '') {
        $domanda .= "\nIndicazioni di chi ha chiesto la revisione — sono la priorità, "
                  . "vengono prima di tutto il resto:\n{$r['indicazioni']}\n";
    }

    $ric = claudeConRicerca(SYS_RILEGGI, $domanda);

    // La ricerca ha impiegato minuti: la connessione potrebbe non
    // esserci più.
    $pdo = db(true);
    $segna = $preparaSegna($pdo);
    $tokIn += $ric['in']; $tokOut += $ric['out'];

    // Una rilettura che non ha consultato niente non è una rilettura.
    // Se il limite del tool si è esaurito il modello scrive volentieri
    // una spiegazione del perché non ce l'ha fatta, e senza questo
    // controllo quella spiegazione diventerebbe il materiale su cui si
    // riscrive un articolo già pubblicato.
    if ($ric['ok'] && !$ric['fonti']) {
        $ric['ok'] = false;
        $ric['errore'] = 'nessuna fonte consultata'
            . ($ric['errori_ricerca'] ? ' (' . implode(', ', array_unique($ric['errori_ricerca'])) . ')' : '');
    }

    if (!$ric['ok'] || trim($ric['testo']) === '') {
        $segna->execute(['errore', null, null, null, null, null, null,
            'rilettura fallita: ' . ($ric['errore'] ?? 'nessun risultato'),
            null, $ric['in'], $ric['out'], $id]);
        allarme(sprintf('revisione %d: rilettura fallita — %s', $id, $ric['errore'] ?? '?'), 'migliora');
        $fallite++; $inCorso = null;
        continue;
    }
    mlog(sprintf('      rilettura: %d fonti, %d caratteri · %d token nuovi, '
        . '%d dalla cache (%.2f €)',
        count($ric['fonti']), mb_strlen($ric['testo']), $ric['in'],
        $ric['cache_letti'] ?? 0, costoEuro($ric['in'], $ric['out'])));

    // --- 2. riscrittura
    $elenco = '';
    foreach ($ric['fonti'] as $url => $titolo) {
        $elenco .= "- $titolo — $url\n";
    }
    $prompt = "L'ARTICOLO COM'È ADESSO\n\n" . $articolo
            . "\n\nMATERIALE RACCOLTO DALLA RILETTURA\n\n{$ric['testo']}\n\n"
            . "PAGINE CONSULTATE\n\n" . ($elenco ?: "(nessuna registrata)\n");
    if (trim((string)$r['indicazioni']) !== '') {
        $prompt .= "\nINDICAZIONI DI CHI HA CHIESTO LA REVISIONE\n{$r['indicazioni']}\n";
    }

    // Il tetto dei token non può essere una costante, ed è il guasto che
    // su cronacheartemis.it ha ucciso una revisione già pagata: la
    // riscrittura non aggiunge un pezzo all'articolo, lo RIMETTE FUORI
    // TUTTO, quindi quello che le serve cresce insieme all'articolo. I
    // 12000 fissi bastavano a un pezzo di 13.000 caratteri e non a uno
    // di 19.000 — e siccome un articolo si allunga a ogni rilettura, era
    // un limite che prima o poi ogni articolo avrebbe incontrato. Il
    // sintomo è caro: la ricerca sul web è già stata fatta e pagata, e
    // la risposta troncata si butta. Quella volta, 0,78 €.
    //
    // Mezzo token per carattere è il doppio di quanto serve a ricopiare
    // l'italiano (circa un terzo), e il doppio ci vuole: c'è il JSON da
    // sfuggire e c'è che una revisione l'articolo lo allunga. I 10000
    // fissi sopra non sono generosità: sono il ragionamento, che con
    // effort medio su un pezzo lungo non è gratis — in quella revisione il
    // testo da riscrivere ne valeva circa 6800, e i 12000 sono finiti
    // lo stesso.
    //
    // Il tetto in alto non è timore del modello ma del conto: un
    // articolo che per un guasto crescesse senza fine si fermerebbe lì
    // invece di scrivere per venti euro. Il pavimento serve ai pezzi
    // brevi, dove a mancare non è mai la copia ma il pensiero.
    $tetto = (int)min(32000, max(16000, mb_strlen($articolo) / 2 + 10000));

    // Una riga prima e una dopo: senza, quando il processo muore non si
    // sa nemmeno se la chiamata era partita.
    mlog(sprintf('      riscrittura: invio %d caratteri, tetto %d token…',
                 mb_strlen($prompt), $tetto));
    $a = claudeJson(SYS_RIVEDI, $prompt, schemaRevisione(), $tetto);
    mlog(sprintf('      riscrittura: %s (%d token in, %d out)',
        $a['ok'] ? 'risposta ricevuta' : 'FALLITA', $a['in'], $a['out']));

    $tin = $ric['in'] + $a['in'];
    $tout = $ric['out'] + $a['out'];
    $tokIn += $a['in']; $tokOut += $a['out'];

    // Stessa cosa dopo la riscrittura, che è l'attesa più lunga.
    $pdo = db(true);
    $segna = $preparaSegna($pdo);
    $fonti = json_encode($ric['fonti'], JSON_UNESCAPED_UNICODE);

    if (!$a['ok'] || empty($a['dati']['titolo_it'])) {
        $segna->execute(['errore', null, null, null, null, null, $fonti,
            'riscrittura fallita: ' . ($a['errore'] ?? 'risposta non conforme'),
            null, $tin, $tout, $id]);
        allarme(sprintf('revisione %d: riscrittura fallita', $id), 'migliora');
        $fallite++; $inCorso = null;
        continue;
    }

    $d = $a['dati'];
    // Il cast a stringa PRIMA di riparaEscape, non dopo: con
    // declare(strict_types=1) un tag che tornasse come numero invece
    // che come stringa farebbe saltare tutto il giro con un TypeError,
    // e per un dettaglio del genere sarebbe un peccato perdere una
    // ricerca sul web già pagata.
    $tagNuovi = array_values(array_filter(array_map(
        fn($t) => trim(mb_strtolower((string)riparaEscape((string)$t))),
        (array)($d['tag'] ?? []))));

    $segna->execute([
        'pronta',
        mb_substr((string)riparaEscape((string)$d['titolo_it']), 0, 300),
        riparaEscape((string)($d['sommario_it'] ?? '')),
        trim((string)riparaEscape((string)($d['corpo_html'] ?? ''))) ?: null,
        $tagNuovi ? json_encode($tagNuovi, JSON_UNESCAPED_UNICODE) : null,
        riparaEscape((string)($d['motivazione'] ?? '')),
        $fonti,
        // «invariato» non è un errore e non è un fallimento: è la
        // risposta che rende credibili tutte le altre. Si segna nella
        // nota perché il pannello la mostri senza rileggere il testo.
        !empty($d['invariato']) ? 'il modello non ha trovato niente da cambiare' : null,
        modello(),
        $tin, $tout, $id,
    ]);

    $fatte++;
    $inCorso = null;
    mlog(sprintf('      ✓ proposta pronta%s (%.2f €)',
        !empty($d['invariato']) ? ' — ma dice che va bene com\'è' : '',
        costoEuro($tin, $tout)));
}
$inCorso = null;

// ---------------------------------------------------------------- chiusura
$pdo = db(true);
$pdo->prepare('INSERT INTO ' . t('run_log') . '
    (job, finito_il, esito, item_elaborati, token_in, token_out, messaggio)
    VALUES (?, NOW(), ?, ?, ?, ?, ?)')
    ->execute(['migliora', $fallite ? 'parziale' : 'ok', $fatte, $tokIn, $tokOut,
               sprintf('%d revisioni pronte, %d fallite', $fatte, $fallite)]);

mlog(sprintf('Fatto in %.0fs — %d pronte, %d fallite · %d token in, %d out, circa %.2f €',
    microtime(true) - $avvio, $fatte, $fallite, $tokIn, $tokOut, costoEuro($tokIn, $tokOut)));
mlog(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
