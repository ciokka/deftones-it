<?php
/**
 * enrich.php — passo 2: raggruppa gli item per evento e scrive le notizie
 * in italiano. Tutto nasce come bozza: niente va online senza un tuo clic.
 *
 * Uso:
 *   php enrich.php              giro completo
 *   php enrich.php --prova      solo il raggruppamento, non scrive nulla
 *                               e non tocca il database (una chiamata sola)
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/claude.php';
require __DIR__ . '/../lib/prompts.php';

@set_time_limit(0);
$soloProva = in_array('--prova', $argv ?? [], true);
$avvio = microtime(true);

$lock = prendiLock('enrich');
if ($lock === false) { logline('Un altro enrich è già in esecuzione — esco.', 'enrich'); exit(0); }

function elog(string $m): void { logline($m, 'enrich'); }

elog($soloProva ? 'Avvio enrich — MODALITÀ PROVA (nessuna scrittura)' : 'Avvio enrich');

$pdo = db();
$tokIn = $tokOut = 0;

// ---------------------------------------------------------------- la coda
$maxItem = (int)(cfg('max_item_per_giro') ?? 60);
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
    elog('Niente in coda — esco.');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}
elog(sprintf('%d item in coda', count($items)));

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
    $pdo->prepare('INSERT INTO ' . t('run_log') . '
        (job, finito_il, esito, token_in, token_out, messaggio)
        VALUES (?, NOW(), ?, ?, ?, ?)')
        ->execute(['enrich', 'errore', $tokIn, $tokOut, $r['errore']]);
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(1);
}

$eventi = $r['dati']['eventi'];
usort($eventi, fn($a, $b) => $b['rilevanza'] <=> $a['rilevanza']);

$soglia = (int)(cfg('soglia_rilevanza') ?? 40);
elog(sprintf('%d eventi individuati (soglia rilevanza: %d)', count($eventi), $soglia));
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

$scarta = $pdo->prepare('UPDATE ' . t('raw_items') . ' SET stato = ?, nota = ? WHERE id = ?');
$segna  = $pdo->prepare('UPDATE ' . t('raw_items') . ' SET stato = \'elaborato\' WHERE id = ?');
$salva  = $pdo->prepare(
    'INSERT INTO ' . t('articles') . '
       (raw_item_id, slug, titolo_it, sommario_it, categoria, tag, rilevanza,
        attendibilita, fonte_nome, fonte_url, stato, modello, uso_token)
     VALUES (?,?,?,?,?,?,?,?,?,?,\'draft\',?,?)'
);

$scritti = $sottoSoglia = $falliti = 0;
$maxEventi = (int)(cfg('max_eventi_per_giro') ?? 8);

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
        $sottoSoglia++;
        continue;
    }
    if ($scritti >= $maxEventi) {
        elog('   raggiunto il tetto di eventi per giro, il resto al prossimo');
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
        cfg('modello') ?: 'claude-opus-5',
        json_encode(['in' => $a['in'], 'out' => $a['out']]),
    ]);

    foreach ($ids as $id) { $segna->execute([$id]); }
    $scritti++;
    elog(sprintf('   ✓ %s', mb_substr($d['titolo_it'], 0, 70)));
}

// ---------------------------------------------------------------- chiusura
$costo = costoEuro($tokIn, $tokOut);
$pdo->prepare('INSERT INTO ' . t('run_log') . '
    (job, finito_il, esito, item_elaborati, token_in, token_out, messaggio)
    VALUES (?, NOW(), ?, ?, ?, ?, ?)')
    ->execute(['enrich', $falliti ? 'parziale' : 'ok', $scritti, $tokIn, $tokOut,
               sprintf('%d bozze, %d eventi sotto soglia, %d falliti', $scritti, $sottoSoglia, $falliti)]);

elog(sprintf('Fatto in %.1fs — %d bozze, %d sotto soglia, %d falliti · %d token in, %d out, circa %.3f €',
    microtime(true) - $avvio, $scritti, $sottoSoglia, $falliti, $tokIn, $tokOut, $costo));
elog(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
