<?php
/**
 * ingest.php — passo 1 della pipeline: scarica i feed, filtra, deduplica,
 * salva in df_raw_items. Nessuna chiamata all'IA, costo zero.
 *
 * Uso:  /opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/cron/ingest.php
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

$avvio = microtime(true);

// Se il giro precedente è ancora in corso, esci senza fare nulla.
$lock = prendiLock('ingest');
if ($lock === false) {
    logline('Un altro ingest è già in esecuzione — esco.');
    exit(0);
}

// Queste due righe servono a te: se il log non compare affatto il
// percorso del binario PHP nel cron è sbagliato; se compare ma con la
// versione sbagliata hai puntato la ea-php81.
logline(sprintf('PHP %s (%s) — %s', PHP_VERSION, PHP_SAPI, PHP_BINARY));

$pdo = db();

$log = $pdo->prepare('INSERT INTO ' . t('run_log') . ' (job) VALUES (?)');
$log->execute(['ingest']);
$runId = (int)$pdo->lastInsertId();

$totNuovi = $totScartati = $totDup = $totVecchi = $totFeed = 0;
$problemi = [];

$sorgenti = $pdo->query(
    'SELECT * FROM ' . t('sources') . ' WHERE attivo = 1 ORDER BY peso DESC'
)->fetchAll();

logline(sprintf('Avvio ingest — %d sorgenti attive', count($sorgenti)));

// -- prepared statements, riusati per ogni item ------------------------
$esiste = $pdo->prepare(
    'SELECT id FROM ' . t('raw_items') . ' WHERE src_url_hash = ? LIMIT 1'
);
$cercaDup = $pdo->prepare(
    'SELECT id FROM ' . t('raw_items') . '
      WHERE titolo_hash = ? AND visto_il >= (NOW() - INTERVAL ? DAY)
        AND stato <> \'duplicato\'
      ORDER BY id ASC LIMIT 1'
);
$inserisci = $pdo->prepare(
    'INSERT IGNORE INTO ' . t('raw_items') . '
       (source_id, url, url_canonico, src_url_hash, url_hash, titolo, titolo_hash,
        estratto, autore, editore, pubblicato_il, stato, duplicato_di)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
);
$okFeed = $pdo->prepare(
    'UPDATE ' . t('sources') . '
        SET ultimo_fetch = NOW(), ultimo_esito = ?, errori_consecutivi = 0,
            etag = ?, last_modified = ?
      WHERE id = ?'
);
$koFeed = $pdo->prepare(
    'UPDATE ' . t('sources') . '
        SET ultimo_fetch = NOW(), ultimo_esito = ?,
            errori_consecutivi = errori_consecutivi + 1,
            attivo = IF(errori_consecutivi + 1 >= 10, 0, attivo)
      WHERE id = ?'
);

$giorni = (int)cfg('dedup_giorni');
$sogliaEta = time() - ((int)(cfg('max_eta_giorni') ?? 14) * 86400);

// ---------------------------------------------------------------------
foreach ($sorgenti as $s) {
    $nome = $s['nome'];
    $r = httpGet($s['url_feed'], $s['etag'], $s['last_modified']);

    if ($r['http'] === 304) {
        $okFeed->execute(['304 non modificato', $s['etag'], $s['last_modified'], $s['id']]);
        logline("  $nome — invariato");
        continue;
    }
    if ($r['error'] !== null || $r['http'] < 200 || $r['http'] >= 300 || $r['body'] === null) {
        $motivo = $r['error'] ?? ('HTTP ' . $r['http']);
        $koFeed->execute([mb_substr($motivo, 0, 250), $s['id']]);
        $problemi[] = "$nome: $motivo";
        logline("  $nome — ERRORE: $motivo");
        continue;
    }

    $items = parseFeed($r['body']);
    if (!$items) {
        $koFeed->execute(['feed illeggibile o vuoto', $s['id']]);
        $problemi[] = "$nome: feed illeggibile";
        logline("  $nome — feed illeggibile o vuoto");
        continue;
    }

    $totFeed++;
    $nuovi = $scartati = $dup = $vecchi = 0;

    foreach ($items as $it) {
        $srcHash = sha1($it['url']);

        // già visto in un giro precedente: non tocchiamo nulla
        $esiste->execute([$srcHash]);
        if ($esiste->fetchColumn() !== false) { continue; }

        // 1. filtro keyword — prima di qualunque richiesta di rete
        $passa = $s['filtra_keyword'] == 0
              || contieneKeyword($it['titolo'] . ' ' . ($it['estratto'] ?? ''));

        $titoloNorm = normalizzaTitolo($it['titolo']);
        $titoloHash = sha1($titoloNorm);
        $stato = 'nuovo';
        $dupDi = null;

        // età: un articolo vecchio viene archiviato, non lavorato
        $dataItem = $it['data'] ? strtotime($it['data']) : null;
        $vecchio = $dataItem !== null && $dataItem !== false && $dataItem < $sogliaEta;

        if (!$passa || $vecchio) {
            $stato = $passa ? 'troppo_vecchio' : 'scartato_keyword';
            if ($vecchio && $passa) { $vecchi++; } else { $scartati++; }
            $urlCanonico = canonicalizza($it['url']);
        } else {
            // 2. stessa notizia già arrivata da un'altra testata?
            $cercaDup->execute([$titoloHash, $giorni]);
            $originale = $cercaDup->fetchColumn();

            if ($originale !== false) {
                $stato = 'duplicato';
                $dupDi = (int)$originale;
                $dup++;
                $urlCanonico = canonicalizza($it['url']);
            } else {
                // Nessun tentativo di sciogliere i link di Google News:
                // verificato che non sono risolvibili (rispondono 200 con
                // una pagina JavaScript, e il payload CBMi... è un blob
                // protobuf senza URL in chiaro). L'editore vero lo prendiamo
                // dal tag <source> del feed, che è affidabile.
                $urlCanonico = canonicalizza($it['url']);
                $nuovi++;
            }
        }

        // La testata da accreditare, in ordine di affidabilità:
        //  1. il tag <source> del feed (è così che Google News dichiara
        //     l'editore vero: "NME", "Louder", "Rolling Stone Italia")
        //  2. il nome della sorgente, per i feed diretti — è già scritto
        //     bene in df_sources, meglio del dominio ricavato dall'URL
        //  3. il dominio, ultima spiaggia
        $editore = $it['editore'] ?? null;
        if ($editore === null && !str_contains($s['url_feed'], 'news.google.com')) {
            $editore = $s['nome'];
        }
        $editore = $editore ?? editoreDaUrl($urlCanonico);
        $editore = $editore !== null ? mb_substr($editore, 0, 120) : null;

        $inserisci->execute([
            $s['id'],
            mb_substr($it['url'], 0, 1000),
            mb_substr($urlCanonico, 0, 1000),
            $srcHash,
            sha1($urlCanonico),
            mb_substr($it['titolo'], 0, 500),
            $titoloHash,
            $it['estratto'],
            $it['autore'] ? mb_substr($it['autore'], 0, 200) : null,
            $editore,
            aDatetime($it['data']),
            $stato,
            $dupDi,
        ]);
    }

    $okFeed->execute([
        sprintf('OK · %d item · %d nuovi', count($items), $nuovi),
        $r['etag'], $r['last_modified'], $s['id'],
    ]);

    $totNuovi += $nuovi; $totScartati += $scartati;
    $totDup += $dup; $totVecchi += $vecchi;
    logline(sprintf('  %-30s %3d item → %2d nuovi, %2d dup, %2d vecchi, %2d fuori tema',
        mb_substr($nome, 0, 30), count($items), $nuovi, $dup, $vecchi, $scartati));

    usleep(500_000);   // mezzo secondo fra un feed e l'altro: educazione
}

// ---------------------------------------------------------------------
$esito = $problemi === [] ? 'ok' : ($totFeed > 0 ? 'parziale' : 'errore');
$pdo->prepare(
    'UPDATE ' . t('run_log') . '
        SET finito_il = NOW(), esito = ?, item_nuovi = ?, messaggio = ?
      WHERE id = ?'
)->execute([
    $esito,
    $totNuovi,
    $problemi ? implode(' | ', $problemi) : null,
    $runId,
]);

logline(sprintf(
    'Fatto in %.1fs — %d nuovi, %d duplicati, %d troppo vecchi, %d fuori tema, %d feed con problemi',
    microtime(true) - $avvio, $totNuovi, $totDup, $totVecchi, $totScartati, count($problemi)
));
logline(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
