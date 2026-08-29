<?php
/**
 * riepilogo.php — il riassunto giornaliero via email.
 *
 *   php riepilogo.php            invia se c'è qualcosa da dire
 *   php riepilogo.php --prova    stampa a video e non invia
 *   php riepilogo.php --forza    invia comunque, anche se non c'è nulla
 *
 * Parte solo quando ci sono bozze nuove o qualcosa si è rotto. Un
 * riepilogo che arriva ogni giorno anche per dire "nessuna novità"
 * smette di essere letto dopo una settimana, e allora non serve più
 * nemmeno quando ha qualcosa di importante da dire.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/web.php';   // per u(), dataIt(), e()

const ORE = 25;      // finestra osservata: un giorno più un margine

$soloProva = in_array('--prova', $argv ?? [], true);
$forza     = in_array('--forza', $argv ?? [], true);
function rlog(string $m): void { logline($m, 'riepilogo'); }

$pdo = db();

// ---------------------------------------------------------- che c'è di nuovo
$q = $pdo->prepare('SELECT rilevanza, categoria, attendibilita, titolo_it,
                           sommario_it, fonte_nome, slug, creato_il
                      FROM ' . t('articles') . "
                     WHERE stato = 'draft' AND categoria <> 'evergreen'
                       AND creato_il >= NOW() - INTERVAL ? HOUR
                     ORDER BY rilevanza DESC");
$q->execute([ORE]);
$nuove = $q->fetchAll();

$inAttesa = (int)$pdo->query('SELECT COUNT(*) FROM ' . t('articles') . "
                               WHERE stato = 'draft'")->fetchColumn();

// ------------------------------------------------------------- e cosa si è rotto
$q = $pdo->prepare('SELECT job, finito_il, esito, messaggio
                      FROM ' . t('run_log') . "
                     WHERE iniziato_il >= NOW() - INTERVAL ? HOUR
                       AND (esito <> 'ok' OR esito IS NULL)
                     ORDER BY id DESC LIMIT 10");
$q->execute([ORE]);
$guasti = $q->fetchAll();

// --------------------------------------------------------------- e quanto è costato
$q = $pdo->prepare('SELECT COALESCE(SUM(token_in),0) AS ti,
                           COALESCE(SUM(token_out),0) AS tou
                      FROM ' . t('run_log') . '
                     WHERE iniziato_il >= NOW() - INTERVAL ? HOUR');
$q->execute([ORE]);
$sp = $q->fetch();
$costo = costoEuro((int)$sp['ti'], (int)$sp['tou']);

// ------------------------------------------------- il guardiano del silenzio
// Questo riepilogo tace quando non c'è niente da dire, ed è voluto. Ma
// tacerebbe allo stesso modo se i cron smettessero di girare — e il
// silenzio diventerebbe un guasto travestito da normalità. Se l'ultimo
// ingest riuscito è vecchio, quello è di per sé una notizia.
$ultimoIngest = $pdo->query('SELECT MAX(finito_il) FROM ' . t('run_log') . "
                              WHERE job = 'ingest' AND esito = 'ok'")->fetchColumn();
$oreFerme = $ultimoIngest ? (time() - strtotime((string)$ultimoIngest)) / 3600 : 999;

if ($oreFerme > 12) {
    $guasti[] = [
        'job'       => 'ingest',
        'finito_il' => $ultimoIngest,
        'esito'     => 'fermo',
        'messaggio' => $ultimoIngest
            ? sprintf('nessuna raccolta riuscita da %.0f ore (ultima: %s)',
                      $oreFerme, dataIt((string)$ultimoIngest))
            : 'non risulta nessuna raccolta riuscita',
    ];
}

if (!$nuove && !$guasti && !$forza) {
    rlog(sprintf('Niente di nuovo, nessun guasto, ultimo ingest %.0f ore fa: non mando niente.',
        $oreFerme));
    exit(0);
}

// ------------------------------------------------------------------ il messaggio
$pannello = rtrim((string)cfg('site_url'), '/') . u('admin/');

$oggetto = $guasti
    ? sprintf('deftones.it · %d guast%s', count($guasti), count($guasti) === 1 ? 'o' : 'i')
    : sprintf('deftones.it · %d nuov%s bozz%s',
        count($nuove), count($nuove) === 1 ? 'a' : 'e', count($nuove) === 1 ? 'a' : 'e');

// --- testo semplice, per chi legge la posta senza HTML
$t = "deftones.it — riepilogo del " . dataIt(date('Y-m-d')) . "\n";
$t .= str_repeat('=', 52) . "\n\n";

if ($guasti) {
    $t .= "GUASTI\n";
    foreach ($guasti as $g) {
        $t .= sprintf("  %s · %s · %s\n", $g['job'], $g['esito'] ?? 'interrotto',
            mb_substr((string)$g['messaggio'], 0, 90));
    }
    $t .= "\n";
}

if ($nuove) {
    $t .= sprintf("%d NUOVE BOZZE\n\n", count($nuove));
    foreach ($nuove as $n) {
        $t .= sprintf("  [%d] %s\n       %s · %s\n       %s\n\n",
            (int)$n['rilevanza'], $n['titolo_it'], $n['categoria'],
            $n['fonte_nome'] ?: '—', mb_substr($n['sommario_it'], 0, 150) . '…');
    }
} else {
    $t .= "Nessuna bozza nuova nelle ultime 24 ore.\n\n";
}

$t .= sprintf("Ultima raccolta riuscita: %.0f ore fa\n", $oreFerme);
$t .= sprintf("In attesa di revisione: %d\n", $inAttesa);
$t .= sprintf("Speso nelle ultime 24 ore: %.2f €\n\n", $costo);
$t .= "Pannello: $pannello\n";

// --- HTML, per chi la legge normalmente
$h = '<div style="background:#000;color:#e8e6e3;font-family:-apple-system,'
   . 'BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
   . 'padding:28px;max-width:640px">';
$h .= '<div style="font-size:13px;letter-spacing:.16em;text-transform:uppercase;'
    . 'color:#8f8d8a;margin-bottom:22px">deftones.it · riepilogo</div>';

if ($guasti) {
    $h .= '<div style="border:1px solid rgba(216,180,74,.5);padding:14px 16px;margin-bottom:24px">'
        . '<div style="color:#d8b44a;font-weight:600;margin-bottom:8px">Qualcosa si è rotto</div>';
    foreach ($guasti as $g) {
        $h .= '<div style="font-size:14px;color:#c3c0bc;margin:4px 0"><b>' . e($g['job']) . '</b> — '
            . e(mb_substr((string)$g['messaggio'], 0, 120)) . '</div>';
    }
    $h .= '</div>';
}

if ($nuove) {
    $h .= '<div style="font-size:22px;margin-bottom:18px">'
        . count($nuove) . ' nuove bozze</div>';
    foreach ($nuove as $n) {
        $h .= '<div style="border-top:1px solid #262629;padding:16px 0">'
            . '<div style="font-size:12px;color:#8f8d8a;margin-bottom:6px">'
            . '<span style="border:1px solid #3ea84b;color:#3ea84b;padding:1px 7px;'
            . 'margin-right:8px">' . (int)$n['rilevanza'] . '</span>'
            . e($n['categoria']) . ' · ' . e($n['fonte_nome'] ?: '—') . '</div>'
            . '<div style="font-size:17px;font-weight:600;margin-bottom:6px">'
            . e($n['titolo_it']) . '</div>'
            . '<div style="font-size:14px;color:#a9a6a2;line-height:1.55">'
            . e(mb_substr($n['sommario_it'], 0, 190)) . '…</div></div>';
    }
} else {
    $h .= '<div style="color:#8f8d8a">Nessuna bozza nuova nelle ultime 24 ore.</div>';
}

$h .= '<div style="border-top:1px solid #262629;margin-top:22px;padding-top:18px;'
    . 'font-size:13px;color:#8f8d8a">'
    . 'Ultima raccolta riuscita: <b style="color:#e8e6e3">'
    . sprintf('%.0f', $oreFerme) . ' ore fa</b><br>'
    . 'In attesa di revisione: <b style="color:#e8e6e3">' . $inAttesa . '</b><br>'
    . 'Speso nelle ultime 24 ore: <b style="color:#e8e6e3">'
    . number_format($costo, 2, ',', '.') . ' €</b></div>';
$h .= '<div style="margin-top:24px"><a href="' . e($pannello) . '" '
    . 'style="display:inline-block;border:1px solid rgba(255,255,255,.35);'
    . 'color:#fff;text-decoration:none;padding:11px 24px;font-size:14px">'
    . 'apri il pannello</a></div>';
$h .= '</div>';

// ------------------------------------------------------------------- invio
if ($soloProva) {
    echo $t, "\n--- l'HTML è lungo ", strlen($h), " byte ---\n";
    echo "Oggetto: $oggetto\nA: ", (string)cfg('email_avvisi'), "\n";
    echo "PROVA: nessun invio.\n";
    exit(0);
}

$ok = inviaMail((string)cfg('email_avvisi'), $oggetto, $t, $h);
rlog($ok
    ? sprintf('Inviato a %s — %d bozze, %d guasti', cfg('email_avvisi'), count($nuove), count($guasti))
    : 'INVIO FALLITO: mail() ha restituito falso');

if (!$ok) {
    allarme('invio del riepilogo fallito', 'riepilogo');
    exit(1);
}
