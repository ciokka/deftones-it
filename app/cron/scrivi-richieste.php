<?php
/**
 * scrivi-richieste.php — scrive gli articoli commissionati dal pannello.
 *
 *   php scrivi-richieste.php          lavora le richieste in attesa
 *   php scrivi-richieste.php --una    ne lavora una sola, per provare
 *
 * Due chiamate per richiesta, non una.
 *
 * Nella prima il modello CERCA sul web e riferisce cosa ha trovato: è la
 * differenza fra un pezzo scritto da fonti e uno scritto a memoria, e su
 * un contenuto destinato a restare anni quella differenza è tutto — una
 * data sbagliata in una scheda evergreen ci resta, e la copiano gli altri.
 *
 * Nella seconda scrive, avendo davanti solo ciò che ha letto. Sono
 * separate anche per un motivo tecnico: le citazioni e il formato
 * strutturato non convivono nella stessa chiamata.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/claude.php';

@set_time_limit(0);
$unaSola = in_array('--una', $argv ?? [], true);

$lock = prendiLock('richieste');
if ($lock === false) { logline('Un altro giro è in corso — esco.', 'richieste'); exit(0); }
function qlog(string $m): void { logline($m, 'richieste'); }

$pdo = db();

// --- richieste rimaste appese ----------------------------------------
// Lo stato passa a 'lavorazione' quando si comincia e a 'fatto' o
// 'errore' quando si finisce. Se il processo muore nel mezzo — un fatal,
// un limite di tempo dell'hosting — la richiesta resta in 'lavorazione'
// e nessun giro successivo la riprende, perché cercano solo 'attesa'.
// Mezz'ora è molto più di quanto serva anche alla ricerca più lunga:
// oltre, è morto qualcosa.
$appese = $pdo->exec('UPDATE ' . t('richieste') . "
    SET stato = 'attesa',
        nota = CONCAT('ripresa dopo un blocco del ', DATE_FORMAT(NOW(), '%d/%m %H:%i'))
  WHERE stato = 'lavorazione'
    AND creato_il < NOW() - INTERVAL 30 MINUTE");
if ($appese) { qlog(sprintf('  %d richieste rimaste appese, rimesse in coda', $appese)); }

$attesa = $pdo->query('SELECT * FROM ' . t('richieste') . "
                        WHERE stato = 'attesa' ORDER BY creato_il LIMIT "
                      . ($unaSola ? 1 : 5))->fetchAll();

if (!$attesa) {
    qlog('Nessuna richiesta in attesa.');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}
qlog(sprintf('%d richieste da lavorare', count($attesa)));

// ---------------------------------------------------------- istruzioni
const SYS_RICERCA = <<<'TXT'
Sei il documentarista di deftones.it, sito italiano di fan dei Deftones.

Ricevi un argomento e devi raccogliere il materiale per un articolo.
Cerca sul web, leggi, e riferisci quello che hai trovato.

Come lavorare:
- parti da fonti dirette: interviste, siti ufficiali, riviste musicali,
  documentazione tecnica dei produttori
- verifica i dati su più fonti quando puoi: modelli di strumenti, date,
  nomi, cifre sono le cose che vengono copiate sbagliate per anni
- distingui i fatti dalle voci, e dillo quando una cosa è incerta
- se qualcosa non riesci a verificarlo, scrivi che non l'hai verificato:
  è un'informazione utile, il silenzio no

Riferisci in italiano, in modo ordinato e denso di fatti. Non scrivere
l'articolo: raccogli il materiale. Indica per ogni informazione da dove
viene, così chi scrive sa cosa può affermare e cosa no.
TXT;

const SYS_SCRITTURA = <<<'TXT'
Scrivi un articolo per deftones.it, sito italiano di fan dei Deftones.

Ricevi il materiale raccolto da una ricerca e l'elenco delle pagine
consultate. Scrivi solo ciò che è nel materiale.

Regole:
- Titolo: 50-90 caratteri, dice di cosa parla. Niente clickbait.
- Sommario: 25-45 parole, per gli elenchi e per l'anteprima social.
- Corpo: HTML semplice — solo <p>, <h2>, <ul>, <li>, <strong>, <em>, <a>.
  Niente attributi di stile, niente classi, niente immagini.
- Lunghezza: 500-900 parole. Meglio corto e denso che lungo e annacquato.
- Struttura il testo con <h2> quando ha più sezioni.
- Dove il materiale segnala un'incertezza, riportala invece di
  appianarla: "secondo X", "non è chiaro se".
- Non inventare niente. Se un dato manca, l'articolo ne fa a meno.
- Tono: appassionato ma sobrio, italiano corrente. Come un fan che sa
  scrivere, non come un comunicato stampa.
- Chiudi con una sezione <h2>Fonti</h2> seguita da <ul> con i link alle
  pagine effettivamente usate, in <a href>. Sono il motivo per cui un
  lettore può fidarsi.

Tag: da 2 a 5, minuscoli, che si ripeteranno nel tempo.
TXT;

$schema = [
    'type' => 'object',
    'properties' => [
        'titolo_it'   => ['type' => 'string'],
        'sommario_it' => ['type' => 'string'],
        'corpo_html'  => ['type' => 'string'],
        'tag'         => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => ['titolo_it','sommario_it','corpo_html','tag'],
    'additionalProperties' => false,
];

// ---------------------------------------------------------------- giro
$segnaStato = $pdo->prepare('UPDATE ' . t('richieste') . '
      SET stato = ?, articolo_id = ?, fonti = ?, nota = ?,
          token_in = ?, token_out = ?, elaborato_il = NOW()
    WHERE id = ?');
$salva = $pdo->prepare('INSERT INTO ' . t('articles') . '
      (slug, titolo_it, sommario_it, corpo_it, categoria, tag, rilevanza,
       attendibilita, stato, modello, uso_token, pubblicato_il, creato_il)
    VALUES (?,?,?,?,\'evergreen\',?,70,\'confermato\',\'draft\',?,?,NOW(),NOW())');

$fatte = $fallite = 0;

// Se il processo muore mentre lavora — fatal, memoria esaurita, il
// gestore dell'hosting che lo termina — questa funzione scatta comunque
// e lascia la richiesta in uno stato onesto invece che appesa.
$inCorso = null;
register_shutdown_function(function () use (&$inCorso, $pdo) {
    if ($inCorso === null) { return; }
    $e = error_get_last();
    $motivo = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
        ? mb_substr($e['message'], 0, 300)
        : 'il processo è terminato prima di finire';
    $pdo->prepare('UPDATE ' . t('richieste') . "
                      SET stato = 'errore', nota = ?, elaborato_il = NOW()
                    WHERE id = ? AND stato = 'lavorazione'")->execute([$motivo, $inCorso]);
});

foreach ($attesa as $r) {
    $id = (int)$r['id'];
    $inCorso = $id;
    $pdo->prepare('UPDATE ' . t('richieste') . " SET stato = 'lavorazione' WHERE id = ?")
        ->execute([$id]);
    qlog(sprintf('  [%d] %s', $id, mb_substr($r['richiesta'], 0, 60)));

    // --- 1. ricerca
    $domanda = "Argomento: {$r['richiesta']}";
    if (trim((string)$r['indicazioni']) !== '') {
        $domanda .= "\n\nIndicazioni di chi ha chiesto l'articolo:\n{$r['indicazioni']}";
    }
    $ric = claudeConRicerca(SYS_RICERCA, $domanda);

    if (!$ric['ok'] || trim($ric['testo']) === '') {
        $segnaStato->execute(['errore', null, null,
            'ricerca fallita: ' . ($ric['errore'] ?? 'nessun risultato'),
            $ric['in'], $ric['out'], $id]);
        allarme(sprintf('richiesta %d: ricerca fallita — %s', $id, $ric['errore'] ?? '?'), 'richieste');
        $fallite++; $inCorso = null;
        continue;
    }
    qlog(sprintf('      ricerca: %d fonti, %d caratteri',
        count($ric['fonti']), mb_strlen($ric['testo'])));

    // --- 2. scrittura
    $elenco = '';
    foreach ($ric['fonti'] as $url => $titolo) {
        $elenco .= "- $titolo — $url\n";
    }
    $prompt = "Argomento: {$r['richiesta']}\n";
    if (trim((string)$r['indicazioni']) !== '') {
        $prompt .= "Indicazioni: {$r['indicazioni']}\n";
    }
    $prompt .= "\nMATERIALE RACCOLTO\n\n{$ric['testo']}\n\n"
             . "PAGINE CONSULTATE\n\n" . ($elenco ?: "(nessuna registrata)\n");

    // Una riga prima e una dopo: senza, quando il processo muore non si
    // sa nemmeno se la chiamata era partita.
    qlog(sprintf('      scrittura: invio %d caratteri di materiale…', mb_strlen($prompt)));
    // 8000 invece di 12000: un articolo da 900 parole ne usa meno di
    // 2000, e il resto è margine per il ragionamento. Un tetto più basso
    // accorcia i tempi e riduce la finestra in cui qualcosa può ucciderci.
    $a = claudeJson(SYS_SCRITTURA, $prompt, $schema, 8000);
    qlog(sprintf('      scrittura: %s (%d token in, %d out)',
        $a['ok'] ? 'risposta ricevuta' : 'FALLITA', $a['in'], $a['out']));
    $tin = $ric['in'] + $a['in'];
    $tout = $ric['out'] + $a['out'];

    if (!$a['ok'] || empty($a['dati']['titolo_it'])) {
        $segnaStato->execute(['errore', null, json_encode($ric['fonti'], JSON_UNESCAPED_UNICODE),
            'scrittura fallita: ' . ($a['errore'] ?? 'risposta non conforme'), $tin, $tout, $id]);
        allarme(sprintf('richiesta %d: scrittura fallita', $id), 'richieste');
        $fallite++; $inCorso = null;
        continue;
    }

    $d = $a['dati'];
    $salva->execute([
        slugUnico((string)riparaEscape($d['titolo_it'])),
        mb_substr((string)riparaEscape($d['titolo_it']), 0, 300),
        riparaEscape($d['sommario_it']),
        riparaEscape($d['corpo_html']),
        json_encode(array_map('riparaEscape', $d['tag']), JSON_UNESCAPED_UNICODE),
        cfg('modello') ?: 'claude-opus-5',
        json_encode(['in' => $tin, 'out' => $tout]),
    ]);
    $articoloId = (int)$pdo->lastInsertId();
    qlog(sprintf('      salvata la bozza %d', $articoloId));

    $segnaStato->execute(['fatto', $articoloId,
        json_encode($ric['fonti'], JSON_UNESCAPED_UNICODE), null, $tin, $tout, $id]);
    $fatte++;
    $inCorso = null;
    qlog(sprintf('      ✓ %s (%.2f €)', mb_substr($d['titolo_it'], 0, 60), costoEuro($tin, $tout)));
}
$inCorso = null;

qlog(sprintf('Fatto — %d scritte, %d fallite', $fatte, $fallite));
qlog(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
