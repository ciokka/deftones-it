<?php
/**
 * recupera-storico.php — riempie l'archivio con l'ultimo anno di notizie.
 *
 *   php recupera-storico.php --prova       conta e basta, non scrive
 *   php recupera-storico.php               raccoglie e mette in coda
 *   php recupera-storico.php --mesi=6      solo gli ultimi sei mesi
 *
 * L'ingest normale guarda solo le ultime due settimane, e i feed delle
 * testate tengono in linea una ventina di articoli: da lì un anno di
 * arretrato non si recupera. Google News però accetta gli intervalli di
 * date nella query — "after:2025-09-01 before:2025-10-01" — quindi si
 * può chiedere un mese alla volta.
 *
 * Gli item entrano in coda ignorando la finestra dei 14 giorni, che qui
 * andrebbe contro lo scopo. Ci pensa poi enrich a raggrupparli e a
 * scrivere gli articoli.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

@set_time_limit(0);
$soloProva = in_array('--prova', $argv ?? [], true);
$mesi = 12;
foreach ($argv ?? [] as $a) {
    if (preg_match('/^--mesi=(\d+)$/', $a, $m)) { $mesi = max(1, min(36, (int)$m[1])); }
}

$lock = prendiLock('storico');
if ($lock === false) { logline('Un altro recupero è in corso — esco.', 'storico'); exit(0); }
function slog(string $m): void { logline($m, 'storico'); }

$pdo = db();

// Le ricerche da fare per ogni mese. Il filtro keyword resta attivo:
// Google allarga le query quando trova poco, e senza filtro entrerebbe
// di tutto.
$RICERCHE = [
    ['q' => 'Deftones',                    'lingua' => 'it'],
    ['q' => 'Deftones',                    'lingua' => 'en'],
    ['q' => '"Chino Moreno"',              'lingua' => 'en'],
    ['q' => 'Deftones Crosses OR Palms',   'lingua' => 'en'],
];

// --- una sorgente dedicata, spenta: l'ingest ordinario non deve toccarla
$q = $pdo->prepare('SELECT id FROM ' . t('sources') . ' WHERE nome = ?');
$q->execute(['Recupero storico']);
$idSorgente = $q->fetchColumn();
if ($idSorgente === false) {
    if ($soloProva) {
        $idSorgente = 0;
    } else {
        $pdo->prepare('INSERT INTO ' . t('sources') . '
              (nome, url_feed, tipo, lingua, peso, filtra_keyword, attivo)
            VALUES (?,?,?,?,?,?,0)')
            ->execute(['Recupero storico', 'https://news.google.com/rss/search?recupero',
                       'rss', 'it', 55, 1]);
        $idSorgente = (int)$pdo->lastInsertId();
        slog('  creata la sorgente "Recupero storico" (spenta per l\'ingest ordinario)');
    }
}

slog(sprintf('%s — ultimi %d mesi, %d ricerche al mese',
    $soloProva ? 'PROVA (nessuna scrittura)' : 'Recupero storico', $mesi, count($RICERCHE)));

// ---------------------------------------------------------------- query
$esiste = $pdo->prepare('SELECT id FROM ' . t('raw_items') . ' WHERE src_url_hash = ? LIMIT 1');
$cercaDup = $pdo->prepare('SELECT id FROM ' . t('raw_items') . '
                            WHERE titolo_hash = ? AND stato <> \'duplicato\'
                            ORDER BY id ASC LIMIT 1');
$inserisci = $pdo->prepare('INSERT IGNORE INTO ' . t('raw_items') . '
       (source_id, url, url_canonico, src_url_hash, url_hash, titolo, titolo_hash,
        estratto, autore, editore, pubblicato_il, stato, duplicato_di)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');

$totNuovi = $totDup = $totScartati = $totVisti = 0;

for ($i = $mesi; $i >= 1; $i--) {
    // primo giorno del mese, calcolato sul primo del mese corrente per
    // non inciampare nei mesi di lunghezza diversa
    $da = date('Y-m-01', strtotime("first day of -$i month"));
    $a  = date('Y-m-01', strtotime('first day of -' . ($i - 1) . ' month'));
    $nuoviMese = $dupMese = $scartMese = 0;

    foreach ($RICERCHE as $r) {
        $loc = $r['lingua'] === 'it' ? 'hl=it&gl=IT&ceid=IT:it' : 'hl=en-US&gl=US&ceid=US:en';
        $url = 'https://news.google.com/rss/search?q='
             . rawurlencode($r['q'] . " after:$da before:$a") . '&' . $loc;

        $res = httpGet($url);
        if ($res['http'] !== 200 || $res['body'] === null) {
            slog(sprintf('  %s · %s — HTTP %d, salto', substr($da, 0, 7), $r['q'], $res['http']));
            continue;
        }

        foreach (parseFeed($res['body']) as $it) {
            $totVisti++;
            $srcHash = sha1($it['url']);
            $esiste->execute([$srcHash]);
            if ($esiste->fetchColumn() !== false) { continue; }

            if (!contieneKeyword($it['titolo'] . ' ' . ($it['estratto'] ?? ''))) {
                $stato = 'scartato_keyword'; $dupDi = null; $scartMese++;
            } else {
                $cercaDup->execute([sha1(normalizzaTitolo($it['titolo']))]);
                $orig = $cercaDup->fetchColumn();
                if ($orig !== false) { $stato = 'duplicato'; $dupDi = (int)$orig; $dupMese++; }
                else { $stato = 'nuovo'; $dupDi = null; $nuoviMese++; }
            }

            if ($soloProva) { continue; }

            $canonico = canonicalizza($it['url']);
            $inserisci->execute([
                $idSorgente,
                mb_substr($it['url'], 0, 1000), mb_substr($canonico, 0, 1000),
                $srcHash, sha1($canonico),
                mb_substr($it['titolo'], 0, 500), sha1(normalizzaTitolo($it['titolo'])),
                $it['estratto'],
                $it['autore'] ? mb_substr($it['autore'], 0, 200) : null,
                $it['editore'] ? mb_substr($it['editore'], 0, 120) : null,
                aDatetime($it['data']),
                $stato, $dupDi,
            ]);
        }
        usleep(600_000);      // educazione verso Google
    }

    $totNuovi += $nuoviMese; $totDup += $dupMese; $totScartati += $scartMese;
    slog(sprintf('  %s — %2d nuovi, %2d duplicati, %2d fuori tema',
        substr($da, 0, 7), $nuoviMese, $dupMese, $scartMese));
}

slog(sprintf('%s — %d item visti · %d nuovi, %d duplicati, %d fuori tema',
    $soloProva ? 'PROVA finita' : 'Fatto', $totVisti, $totNuovi, $totDup, $totScartati));

if ($soloProva) {
    slog('Nessuna scrittura. Togli --prova per mettere in coda.');
} else {
    $inCoda = (int)$pdo->query('SELECT COUNT(*) FROM ' . t('raw_items') . '
                                 WHERE stato = \'nuovo\'')->fetchColumn();
    slog(sprintf('In coda per enrich: %d item.', $inCoda));
    slog('Lancia enrich con --tutto per smaltirli, o aspetta i giri ordinari.');
}
slog(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
