<?php
/**
 * enrich.php — passo 2: raggruppa gli item per evento e scrive le notizie
 * in italiano. Tutto nasce come bozza: niente va online senza un tuo clic.
 *
 * Uso:
 *   php enrich.php              giro completo
 *   php enrich.php --prova      solo il raggruppamento, non scrive nulla
 *                               e non tocca il database (una chiamata sola)
 *   php enrich.php --soglia=65  alza l'asticella per questo giro soltanto
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/claude.php';
require __DIR__ . '/../lib/prompts.php';

@set_time_limit(0);
$soloProva = in_array('--prova', $argv ?? [], true);
// --tutto ripete il ciclo finché la coda è vuota: serve dopo un recupero
// storico, dove la coda ha centinaia di item e i giri ordinari da otto
// articoli l'uno ci metterebbero giorni.
$tutto = in_array('--tutto', $argv ?? [], true);
$MAX_GIRI = 40;                       // rete di sicurezza contro i cicli infiniti
$avvio = microtime(true);

$lock = prendiLock('enrich');
if ($lock === false) { logline('Un altro enrich è già in esecuzione — esco.', 'enrich'); exit(0); }

function elog(string $m): void { logline($m, 'enrich'); }

elog($soloProva ? 'Avvio enrich — MODALITÀ PROVA (nessuna scrittura)' : 'Avvio enrich');

$pdo = db();
$tokIn = $tokOut = 0;

// ---------------------------------------------- preparazione, una volta sola
$maxItem = (int)(cfg('max_item_per_giro') ?? 60);
$soglia = (int)(cfg('soglia_rilevanza') ?? 40);
// Alzabile per un giro solo. Serve ai recuperi d'archivio: quattro anni
// di arretrato passati con la soglia di tutti i giorni riempirebbero il
// sito di notizie che nel 2023 valevano una riga e oggi zero — e
// costerebbero una chiamata ciascuna. Cambiare config.php per due giri e
// poi rimetterlo com'era è il genere di cosa che ci si dimentica.
foreach ($argv ?? [] as $x) {
    if (preg_match('/^--soglia=(\d{1,3})$/', $x, $m)) {
        $soglia = max(0, min(100, (int)$m[1]));
    }
}
$maxEventi = (int)(cfg('max_eventi_per_giro') ?? 8);
$scritti = $sottoSoglia = $falliti = 0;
$giro = 0;

// Quello che il sito ha già scritto, per non riscriverlo.
//
// enrich marca "elaborato" ogni item che consuma, e finché la notizia
// arriva una volta sola basta. Ma la stessa notizia torna: da un'altra
// testata, con un altro indirizzo, o ripescata da un recupero
// d'archivio. Allora diventa un item nuovo, forma un evento nuovo e si
// ritrova riscritta da zero — con l'aggravante che la chiamata l'abbiamo
// pagata. È successo: otto bozze, tre delle quali erano già online.
//
// Due liste. Gli indirizzi delle fonti già usate fermano il caso esatto
// prima di spendere. I titoli già pubblicati fermano il caso somigliante
// dopo la scrittura: lì la chiamata è persa, ma la bozza doppia no.
$urlUsati = $titoliUsati = [];
foreach ($pdo->query('SELECT titolo_it, fonte_url FROM ' . t('articles'))->fetchAll() as $r) {
    if ($r['fonte_url']) { $urlUsati[sha1((string)$r['fonte_url'])] = true; }
    $t = normalizzaTitolo((string)$r['titolo_it']);
    if ($t !== '') { $titoliUsati[$t] = true; }
}
elog(sprintf('  %d articoli già scritti, non li rifaccio', count($titoliUsati)));

/**
 * Un titolo che dice la stessa cosa di uno già pubblicato.
 *
 * Non l'uguaglianza: due testate raccontano la stessa notizia con parole
 * diverse, e la traduzione in italiano le allontana ancora. Il
 * confronto è sulla somiglianza, e la soglia sta alta — meglio una
 * bozza doppia da scartare a mano che una notizia vera buttata via
 * perché somigliava a un'altra.
 */
function giaScritto(string $titolo, array $titoliUsati): bool
{
    $t = normalizzaTitolo($titolo);
    if ($t === '') { return false; }
    if (isset($titoliUsati[$t])) { return true; }
    foreach (array_keys($titoliUsati) as $vecchio) {
        similar_text($t, $vecchio, $quanto);
        if ($quanto >= 82.0) { return true; }
    }
    return false;
}

$scarta = $pdo->prepare('UPDATE ' . t('raw_items') . ' SET stato = ?, nota = ? WHERE id = ?');
$segna  = $pdo->prepare('UPDATE ' . t('raw_items') . ' SET stato = \'elaborato\' WHERE id = ?');
$salva  = $pdo->prepare(
    // pubblicato_il viene dalla fonte, non da adesso: la data
    // dell'articolo è quella del fatto che racconta. Senza, un recupero
    // di arretrato daterebbe a oggi notizie di un anno fa.
    'INSERT INTO ' . t('articles') . '
       (raw_item_id, slug, titolo_it, sommario_it, categoria, tag, rilevanza,
        attendibilita, fonte_nome, fonte_url, pubblicato_il, stato, modello, uso_token)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,\'draft\',?,?)'
);

// ---------------------------------------------------------------- il ciclo
do {
$giro++;
$items = $pdo->query(
    'SELECT r.id, r.titolo, r.estratto, r.url_canonico, r.pubblicato_il,
            COALESCE(r.editore, s.nome) AS fonte, s.peso
       FROM ' . t('raw_items') . ' r
       JOIN ' . t('sources') . ' s ON s.id = r.source_id
      WHERE r.stato = \'nuovo\'
      ORDER BY r.pubblicato_il DESC
      LIMIT ' . $maxItem
)->fetchAll();

if (!$items) {
    if ($giro === 1) { elog('Niente in coda — esco.'); }
    break;
}
elog(sprintf('%sgiro %d — %d item in coda', $tutto ? '' : '', $giro, count($items)));

// ------------------------------------------------- chiamata 1: raggruppa
$elenco = '';
foreach ($items as $it) {
    $elenco .= sprintf(
        "[%d] (%s, %s)\n%s\n%s\n\n",
        $it['id'],
        $it['fonte'],
        $it['pubblicato_il'] ? substr($it['pubblicato_il'], 0, 10) : 'senza data',
        $it['titolo'],
        mb_substr((string)$it['estratto'], 0, 400)
    );
}

$r = claudeJson(SYS_RAGGRUPPA, "Raggruppa queste notizie per evento.\n\n" . $elenco,
                schemaRaggruppa(), 8000);
$tokIn += $r['in']; $tokOut += $r['out'];

if (!$r['ok'] || !isset($r['dati']['eventi'])) {
    allarme('raggruppamento fallito: ' . ($r['errore'] ?? 'risposta non conforme'), 'enrich');
    $falliti++;
    break;
}

$eventi = $r['dati']['eventi'];
usort($eventi, fn($a, $b) => $b['rilevanza'] <=> $a['rilevanza']);

elog(sprintf('  %d eventi individuati (soglia %d)', count($eventi), $soglia));
foreach ($eventi as $e) {
    elog(sprintf('   %3d  %-12s %2d fonti  %s',
        $e['rilevanza'], $e['attendibilita'], count($e['item_ids']),
        mb_substr($e['descrizione'], 0, 60)));
}

if ($soloProva) {
    elog(sprintf('PROVA finita in %.1fs — %d token in, %d out, circa %.3f €',
        microtime(true) - $avvio, $tokIn, $tokOut, costoEuro($tokIn, $tokOut)));
    elog(str_repeat('-', 60));
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}

// ---------------------------------------------- chiamate 2..K: scrittura
$perId = [];
foreach ($items as $it) { $perId[(int)$it['id']] = $it; }

$scrittiGiro = $sottoSogliaGiro = 0;

foreach ($eventi as $e) {
    $ids = array_values(array_intersect(
        array_map('intval', $e['item_ids']), array_keys($perId)
    ));
    if (!$ids) { continue; }

    // sotto soglia: archiviamo gli item senza spendere una chiamata
    if ((int)$e['rilevanza'] < $soglia) {
        foreach ($ids as $id) {
            $scarta->execute(['scartato_keyword',
                sprintf('rilevanza %d < %d — %s', $e['rilevanza'], $soglia, $e['descrizione']), $id]);
        }
        $sottoSoglia++; $sottoSogliaGiro++;
        continue;
    }
    if ($scrittiGiro >= $maxEventi) {
        elog('   tetto di eventi per giro raggiunto');
        break;
    }

    // Come fonte principale vogliamo un item LINKABILE: i link di Google
    // News non sono risolvibili, quindi a parità di evento preferiamo un
    // item arrivato da un feed diretto, scegliendo quello di peso maggiore.
    // Solo se non ce ne sono ripieghiamo sulla scelta del modello.
    $princId = isset($perId[(int)$e['id_principale']]) ? (int)$e['id_principale'] : $ids[0];
    $miglior = null;
    foreach ($ids as $id) {
        if (str_contains((string)$perId[$id]['url_canonico'], 'news.google.com')) { continue; }
        if ($miglior === null || (int)$perId[$id]['peso'] > (int)$perId[$miglior]['peso']) {
            $miglior = $id;
        }
    }
    if ($miglior !== null) { $princId = $miglior; }
    $princ = $perId[$princId];

    $fonti = '';
    foreach ($ids as $id) {
        $it = $perId[$id];
        $fonti .= sprintf("--- %s (%s)\n%s\n%s\n\n",
            $it['fonte'],
            $it['pubblicato_il'] ? substr($it['pubblicato_il'], 0, 10) : 'senza data',
            $it['titolo'],
            mb_substr((string)$it['estratto'], 0, 900));
    }

    // Prima di spendere: se la fonte principale è già stata usata per un
    // articolo, questa notizia l'abbiamo già raccontata.
    if (isset($urlUsati[sha1((string)$princ['url_canonico'])])) {
        foreach ($ids as $id) {
            $scarta->execute(['duplicato',
                'già scritto da ' . mb_substr((string)$princ['url_canonico'], 0, 200), $id]);
        }
        elog('   già scritto (stessa fonte) — ' . mb_substr($e['descrizione'], 0, 50));
        continue;
    }

    $prompt = "Evento: {$e['descrizione']}\n"
            . "Categoria: {$e['categoria']}\n"
            . "Attendibilità: {$e['attendibilita']}\n\n"
            . "Fonti:\n\n" . $fonti;

    $a = claudeJson(SYS_SCRIVI, $prompt, schemaScrivi(), 4000);
    $tokIn += $a['in']; $tokOut += $a['out'];

    if (!$a['ok'] || !isset($a['dati']['titolo_it'])) {
        allarme('scrittura fallita: ' . ($a['errore'] ?? 'risposta non conforme'), 'enrich');
        $falliti++;
        continue;
    }

    $d = $a['dati'];

    // Dopo la scrittura, perché il titolo in italiano prima non c'era. La
    // chiamata a questo punto è spesa, ma una bozza doppia costa più di
    // una chiamata: costa il tempo di chi la rilegge.
    if (giaScritto((string)$d['titolo_it'], $titoliUsati)) {
        foreach ($ids as $id) {
            $scarta->execute(['duplicato',
                'già scritto: ' . mb_substr((string)$d['titolo_it'], 0, 200), $id]);
        }
        elog('   già scritto (titolo simile) — ' . mb_substr((string)$d['titolo_it'], 0, 50));
        continue;
    }

    // La testata da accreditare viene dal tag <source> del feed (colonna
    // editore); il nome del feed è l'ultima spiaggia.
    $fonteUrl  = (string)$princ['url_canonico'];
    $fonteNome = (string)$princ['fonte'];

    $salva->execute([
        $princId ?: null,
        slugUnico((string)riparaEscape($d['titolo_it'])),
        mb_substr((string)riparaEscape($d['titolo_it']), 0, 300),
        riparaEscape($d['sommario_it']),
        $e['categoria'],
        json_encode(array_map('riparaEscape', $d['tag']), JSON_UNESCAPED_UNICODE),
        (int)$e['rilevanza'],
        $e['attendibilita'],
        mb_substr($fonteNome, 0, 120),
        mb_substr($fonteUrl, 0, 1000),
        $princ['pubblicato_il'] ?: date('Y-m-d H:i:s'),
        cfg('modello') ?: 'claude-opus-5',
        json_encode(['in' => $a['in'], 'out' => $a['out']]),
    ]);

    foreach ($ids as $id) { $segna->execute([$id]); }
    // Il giro successivo non deve riscriverlo: le liste vanno tenute
    // aggiornate mentre si lavora, non solo caricate all'inizio.
    $urlUsati[sha1($fonteUrl)] = true;
    $titoliUsati[normalizzaTitolo((string)$d['titolo_it'])] = true;
    $scritti++; $scrittiGiro++;
    elog(sprintf('   ✓ %s', mb_substr($d['titolo_it'], 0, 70)));
}

// il ciclo prosegue solo con --tutto, e solo se il giro ha prodotto
// qualcosa: senza questa condizione una coda di soli eventi sotto soglia
// girerebbe a vuoto fino al tetto
} while ($tutto && $giro < $MAX_GIRI && ($scrittiGiro > 0 || $sottoSogliaGiro > 0));

// ---------------------------------------------------------------- chiusura
$costo = costoEuro($tokIn, $tokOut);
$pdo->prepare('INSERT INTO ' . t('run_log') . '
    (job, finito_il, esito, item_elaborati, token_in, token_out, messaggio)
    VALUES (?, NOW(), ?, ?, ?, ?, ?)')
    ->execute(['enrich', $falliti ? 'parziale' : 'ok', $scritti, $tokIn, $tokOut,
               sprintf('%d bozze, %d eventi sotto soglia, %d falliti', $scritti, $sottoSoglia, $falliti)]);

elog(sprintf('Fatto in %.0fs · %d giri — %d bozze, %d sotto soglia, %d falliti · '
    . '%d token in, %d out, circa %.2f €',
    microtime(true) - $avvio, $giro, $scritti, $sottoSoglia, $falliti, $tokIn, $tokOut, $costo));
elog(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
