<?php
/**
 * dischi.php — mette in piedi la discografia.
 *
 * Tre mestieri separati, perché hanno costi e rischi diversi:
 *
 *   --tracklist   prende da MusicBrainz l'elenco dei brani con le durate,
 *                 la data d'uscita e l'etichetta. Gratis, e soprattutto
 *                 verificabile: è un registro, non una memoria. Le
 *                 tracklist non si chiedono a un modello linguistico.
 *
 *   --copertine   scarica le copertine ufficiali dal Cover Art Archive.
 *                 Gratis.
 *
 *   --schede      fa scrivere al modello il racconto del disco, con
 *                 ricerca sul web. QUESTO COSTA: conta qualche decina di
 *                 centesimi per disco. Di suo ne fa uno solo per volta;
 *                 con --tutte li fa tutti.
 *
 * Opzioni:
 *   --prova       dice cosa farebbe senza scrivere niente
 *   --solo=slug   lavora su un disco solo (es. --solo=white-pony)
 *   --rifai       rifà anche quelli già fatti
 *
 * Uso:  /opt/cpanel/ea-php83/root/usr/bin/php -q \
 *         /home/bpdefton/deftones/app/cron/dischi.php --tracklist
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/web.php';       // per cacheSvuota()
require __DIR__ . '/../lib/claude.php';
require __DIR__ . '/../lib/prompts.php';
require __DIR__ . '/../lib/copertine.php'; // per copertinaDisco()

$avvio = microtime(true);
$opz = $argv ?? [];
$soloProva  = in_array('--prova', $opz, true);
$rifai      = in_array('--rifai', $opz, true);
$tutte      = in_array('--tutte', $opz, true);
$faiTrack   = in_array('--tracklist', $opz, true);
$faiCopert  = in_array('--copertine', $opz, true);
$faiSchede  = in_array('--schede', $opz, true);
$solo = null;
foreach ($opz as $o) { if (preg_match('/^--solo=([a-z0-9-]+)$/', $o, $m)) { $solo = $m[1]; } }

if (!$faiTrack && !$faiCopert && !$faiSchede) {
    fwrite(STDERR, "Serve almeno fra --tracklist, --copertine e --schede.\n");
    exit(2);
}

$lock = prendiLock('dischi');
if ($lock === false) { logline('Un altro giro è in corso — esco.', 'dischi'); exit(0); }
function dlog(string $m): void { logline($m, 'dischi'); }

dlog(sprintf('PHP %s%s', PHP_VERSION, $soloProva ? ' — PROVA' : ''));
$pdo = db();

$cartella = rtrim((string)(cfg('media_dir') ?: '/home/bpdefton/public_html/media'), '/') . '/dischi';
if (!$soloProva && $faiCopert && !is_dir($cartella) && !@mkdir($cartella, 0755, true) && !is_dir($cartella)) {
    allarme('Non riesco a creare ' . $cartella, 'dischi');
    exit(1);
}

$dove = $solo ? 'WHERE slug = ' . $pdo->quote($solo) : '';
$dischi = $pdo->query('SELECT * FROM ' . t('albums') . " $dove ORDER BY ordine, anno")->fetchAll();
dlog(sprintf('%d dischi in elenco', count($dischi)));

// ---------------------------------------------------------- MusicBrainz

/** MusicBrainz chiede un massimo di una richiesta al secondo. */
function mb(string $percorso): ?array
{
    static $ultima = 0.0;
    $attesa = 1.1 - (microtime(true) - $ultima);
    if ($attesa > 0) { usleep((int)($attesa * 1000000)); }
    $ultima = microtime(true);

    $r = httpGet('https://musicbrainz.org/ws/2/' . $percorso);
    if ($r['http'] !== 200 || $r['body'] === null) { return null; }
    $d = json_decode($r['body'], true);
    return is_array($d) ? $d : null;
}

/**
 * Fra le venticinque pubblicazioni dello stesso disco — ristampe, promo,
 * edizioni giapponesi con due bonus track — si sceglie quella giusta
 * per CONSENSO, non prendendo la più vecchia.
 *
 * Il motivo sta in White Pony. MusicBrainz dichiara come first-release-date
 * il 27 aprile 2000, sulla fede di una sola pubblicazione; altre sette
 * dicono 20 giugno 2000, che è la data vera. Prendere la più vecchia
 * significava mettere in pagina la data sbagliata — e infatti la scheda
 * scritta cercando sul web diceva un'altra cosa dell'intestazione.
 *
 * Quindi: si guardano solo le date complete dell'anno più antico, si
 * conta quante pubblicazioni portano ciascuna, e vince quella su cui
 * sono d'accordo in più. A parità, la più vecchia.
 */
function dataDiConsenso(array $rel): ?string
{
    $conta = [];
    foreach ($rel as $r) {
        $d = (string)($r['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { continue; }
        $conta[$d] = ($conta[$d] ?? 0) + 1;
    }
    if (!$conta) { return null; }

    // Solo l'anno d'uscita: le ristampe del 2020 non devono entrare nel
    // conteggio di un disco del 2000.
    $anno = mb_substr(min(array_keys($conta)), 0, 4);
    $conta = array_filter($conta, fn($d) => str_starts_with($d, $anno), ARRAY_FILTER_USE_KEY);

    $vincitrice = null; $max = 0;
    foreach ($conta as $d => $n) {
        if ($n > $max || ($n === $max && $d < $vincitrice)) { $vincitrice = $d; $max = $n; }
    }
    return $vincitrice;
}

/** La pubblicazione da cui prendere tracklist ed etichetta. */
function pubblicazioneMigliore(array $rel, ?string $data): ?array
{
    $filtra = function (array $r) use ($data): bool {
        return $data === null || (string)($r['date'] ?? '') === $data;
    };
    foreach ([
        fn(array $r) => $filtra($r) && ($r['status'] ?? '') === 'Official',
        fn(array $r) => $filtra($r),
        fn(array $r) => ($r['status'] ?? '') === 'Official' && !empty($r['date']),
        fn(array $r) => !empty($r['date']),
    ] as $regola) {
        $buone = array_values(array_filter($rel, $regola));
        if ($buone) {
            usort($buone, fn($a, $b) => strcmp((string)($a['date'] ?? '9999'), (string)($b['date'] ?? '9999')));
            return $buone[0];
        }
    }
    return $rel[0] ?? null;
}

function durataIt(?int $ms): ?string
{
    if (!$ms) { return null; }
    return sprintf('%d:%02d', intdiv($ms, 60000), intdiv($ms, 1000) % 60);
}

// =====================================================================
if ($faiTrack) {
    // La data la sovrascriviamo: quella del seed veniva dal
    // first-release-date di MusicBrainz, che per White Pony è sbagliato.
    $agg = $pdo->prepare('UPDATE ' . t('albums') . '
         SET tracklist = ?, data_uscita = COALESCE(?, data_uscita),
             anno = COALESCE(?, anno), etichetta = COALESCE(?, etichetta)
       WHERE id = ?');
    $fatti = $saltati = 0;

    foreach ($dischi as $d) {
        if (empty($d['mbid']))                      { dlog('  senza mbid: ' . $d['slug']); $saltati++; continue; }
        if ($d['tracklist'] && !$rifai)             { $saltati++; continue; }

        $rg = mb('release?release-group=' . $d['mbid'] . '&fmt=json&limit=100&inc=labels');
        $data = dataDiConsenso($rg['releases'] ?? []);
        $scelta = pubblicazioneMigliore($rg['releases'] ?? [], $data);
        if (!$scelta) { dlog('  nessuna pubblicazione: ' . $d['titolo']); $saltati++; continue; }

        $piena = mb('release/' . $scelta['id'] . '?inc=recordings+labels&fmt=json');
        $brani = [];
        foreach ($piena['media'] ?? [] as $supporto) {
            foreach ($supporto['tracks'] ?? [] as $t) {
                $brani[] = [
                    'n'       => count($brani) + 1,
                    'titolo'  => (string)$t['title'],
                    'durata'  => durataIt(isset($t['length']) ? (int)$t['length'] : null),
                ];
            }
        }
        if (!$brani) { dlog('  tracklist vuota: ' . $d['titolo']); $saltati++; continue; }

        $etichetta = $piena['label-info'][0]['label']['name'] ?? null;
        dlog(sprintf('  %-26s %2d brani   %s%s   %s', mb_substr($d['titolo'], 0, 26),
            count($brani), $data ?? '?',
            ($data && $d['data_uscita'] && $data !== $d['data_uscita']) ? ' (era ' . $d['data_uscita'] . ')' : '',
            $etichetta ?? ''));

        if (!$soloProva) {
            $agg->execute([
                json_encode($brani, JSON_UNESCAPED_UNICODE),
                $data, $data ? (int)mb_substr($data, 0, 4) : null, $etichetta, $d['id'],
            ]);
        }
        $fatti++;
    }
    dlog(sprintf('Tracklist: %d fatte, %d saltate', $fatti, $saltati));
}

// =====================================================================
if ($faiCopert) {
    $agg = $pdo->prepare('UPDATE ' . t('albums') . ' SET copertina = ? WHERE id = ?');
    $fatti = $saltati = 0;

    foreach ($dischi as $d) {
        if (empty($d['mbid']))                { $saltati++; continue; }
        if ($d['copertina'] && !$rifai)       { $saltati++; continue; }

        $dati = $soloProva ? 'x' : copertinaDisco((string)$d['mbid']);
        if ($dati === null) { dlog('  nessuna copertina: ' . $d['titolo']); $saltati++; continue; }

        $rel = '/media/dischi/' . $d['slug'] . '.jpg';
        if (!$soloProva && @file_put_contents($cartella . '/' . $d['slug'] . '.jpg', $dati) === false) {
            dlog('  non scritta: ' . $rel); $saltati++; continue;
        }
        dlog(sprintf('  %-26s %s', mb_substr($d['titolo'], 0, 26), $rel));
        if (!$soloProva) { $agg->execute([$rel, $d['id']]); }
        $fatti++;
    }
    dlog(sprintf('Copertine: %d scaricate, %d saltate', $fatti, $saltati));
}

// =====================================================================
if ($faiSchede) {
    $agg = $pdo->prepare('UPDATE ' . t('albums') . ' SET descrizione_it = ? WHERE id = ?');
    $fatti = 0; $costo = 0.0;

    foreach ($dischi as $d) {
        if ($d['descrizione_it'] && !$rifai) { continue; }
        if ($fatti >= 1 && !$tutte) { break; }

        $tracce = json_decode((string)$d['tracklist'], true) ?: [];
        $elenco = implode(', ', array_map(fn($t) => $t['titolo'], array_slice($tracce, 0, 20)));

        $prompt = "Disco: {$d['titolo']} dei Deftones"
                . ($d['anno'] ? ", uscito nel {$d['anno']}" : '')
                . ($d['etichetta'] ? ", per {$d['etichetta']}" : '') . ".\n"
                . ($elenco ? "Brani: $elenco\n" : '')
                . "\nCerca sul web e scrivi la scheda di questo disco.";

        dlog('  scrivo: ' . $d['titolo'] . ' …');
        if ($soloProva) { dlog('    (prova: nessuna chiamata)'); $fatti++; continue; }

        $r = claudeConRicerca(SYS_DISCO, $prompt, 6);
        // La ricerca può durare minuti: la connessione potrebbe non esserci più.
        $pdo = db(true);
        $agg = $pdo->prepare('UPDATE ' . t('albums') . ' SET descrizione_it = ? WHERE id = ?');

        if (!$r['ok'] || trim($r['testo']) === '') {
            allarme('Scheda fallita per ' . $d['titolo'] . ': ' . ($r['errore'] ?? 'testo vuoto'), 'dischi');
            continue;
        }
        $c = costoEuro($r['in'], $r['out']);
        $costo += $c;
        dlog(sprintf('    %d parole, %d fonti, %.2f €',
            str_word_count(strip_tags($r['testo'])), count($r['fonti']), $c));

        $agg->execute([riparaEscape(trim($r['testo'])), $d['id']]);
        $fatti++;
    }
    dlog(sprintf('Schede: %d scritte, %.2f € in tutto', $fatti, $costo));
    if ($fatti && !$tutte) { dlog('Rilancia per il disco successivo, o usa --tutte.'); }
}

if (!$soloProva) { cacheSvuota(); }
dlog(sprintf('Fatto in %.1fs', microtime(true) - $avvio));
dlog(str_repeat('-', 60));
if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
