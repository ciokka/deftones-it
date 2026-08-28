<?php
/**
 * index.php — punto d'ingresso unico del sito.
 * Ogni richiesta passa di qui: .htaccess manda tutto a questo file.
 */
declare(strict_types=1);

// Dove sta l'applicazione. Sul server i file pubblici sono in
// public_html/v2/ mentre il codice è in /home/bpdefton/deftones/app/:
// le due cartelle NON sono affiancate, quindi il percorso è esplicito.
$app = '/home/bpdefton/deftones/app';

// Ripiego per lo sviluppo in locale, dove invece sono affiancate.
if (!is_file($app . '/lib/bootstrap.php')) {
    $app = dirname(__DIR__) . '/app';
}

// Meglio un messaggio chiaro che un 500 muto: se sposti le cartelle,
// questa riga ti dice esattamente cosa correggere.
if (!is_file($app . '/lib/bootstrap.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Applicazione non trovata.\n\n"
       . "Cercata in: $app/lib/bootstrap.php\n"
       . 'Correggi la variabile $app in cima a ' . __FILE__ . "\n");
}

require $app . '/lib/bootstrap.php';
require $app . '/lib/web.php';

// ---------------------------------------------------------------- rotta
$percorso = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim((string)(cfg('base_url') ?? ''), '/');
if ($base !== '' && str_starts_with($percorso, $base)) {
    $percorso = substr($percorso, strlen($base));
}
$percorso = '/' . trim(rawurldecode($percorso), '/');
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ------------------------------------------------------- cache pubblica
// Serviamo dalla cache solo le pagine pubbliche a visitatori non loggati.
$cachabile = $metodo === 'GET' && !str_starts_with($percorso, '/admin') && !loggato();
if ($cachabile && ($html = cacheLeggi($percorso)) !== null) {
    header('X-Cache: hit');
    echo $html;
    exit;
}

$pdo = db();
$html = null;

// ---------------------------------------------------------------- rotte

// --- home
if ($percorso === '/') {
    $articoli = $pdo->query(
        'SELECT slug, titolo_it, sommario_it, categoria, attendibilita,
                fonte_nome, pubblicato_il
           FROM ' . t('articles') . '
          WHERE stato = \'pubblicato\'
          ORDER BY pubblicato_il DESC LIMIT 21'
    )->fetchAll();

    $html = render('home', ['articoli' => $articoli], [
        'titolo'      => 'deftones.it — notizie sui Deftones in italiano',
        'descrizione' => 'Notizie sui Deftones in italiano, aggiornate ogni giorno: '
                       . 'tour, uscite, interviste. Riassunti con link alle fonti originali.',
    ]);
}

// --- feed RSS
elseif ($percorso === '/feed.xml') {
    $articoli = $pdo->query(
        'SELECT slug, titolo_it, sommario_it, pubblicato_il, fonte_nome
           FROM ' . t('articles') . '
          WHERE stato = \'pubblicato\'
          ORDER BY pubblicato_il DESC LIMIT 30'
    )->fetchAll();

    $sito = (string)cfg('site_url');
    header('Content-Type: application/rss+xml; charset=utf-8');
    $x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $x .= '<rss version="2.0"><channel>' . "\n";
    $x .= '<title>' . e(cfg('site_name')) . "</title>\n";
    $x .= '<link>' . e($sito) . "</link>\n";
    $x .= "<description>Notizie sui Deftones in italiano</description>\n";
    $x .= "<language>it-IT</language>\n";
    foreach ($articoli as $a) {
        $link = $sito . u('notizie/' . $a['slug'] . '/');
        $x .= "<item>\n";
        $x .= '<title>' . e($a['titolo_it']) . "</title>\n";
        $x .= '<link>' . e($link) . "</link>\n";
        $x .= '<guid isPermaLink="true">' . e($link) . "</guid>\n";
        $x .= '<pubDate>' . date(DATE_RSS, (int)strtotime((string)$a['pubblicato_il'])) . "</pubDate>\n";
        $x .= '<description>' . e($a['sommario_it']) . "</description>\n";
        $x .= "</item>\n";
    }
    $x .= "</channel></rss>\n";
    if ($cachabile) { cacheScrivi($percorso, $x); }
    echo $x;
    exit;
}

// --- scheda notizia
elseif (preg_match('#^/notizie/([a-z0-9-]+)$#', $percorso, $m)) {
    $q = $pdo->prepare('SELECT * FROM ' . t('articles') . '
                         WHERE slug = ? AND stato = \'pubblicato\' LIMIT 1');
    $q->execute([$m[1]]);
    $a = $q->fetch();
    if (!$a) { pagina404(); }

    $alt = $pdo->prepare('SELECT slug, titolo_it, pubblicato_il FROM ' . t('articles') . '
                           WHERE stato = \'pubblicato\' AND id <> ?
                           ORDER BY pubblicato_il DESC LIMIT 4');
    $alt->execute([$a['id']]);

    $html = render('articolo', ['a' => $a, 'altri' => $alt->fetchAll()], [
        'titolo'      => $a['titolo_it'] . ' — deftones.it',
        'descrizione' => mb_substr($a['sommario_it'], 0, 160),
        'canonico'    => cfg('site_url') . u('notizie/' . $a['slug'] . '/'),
    ]);
}

// --- categoria
elseif (preg_match('#^/categoria/([a-z]+)$#', $percorso, $m)) {
    $q = $pdo->prepare('SELECT slug, titolo_it, sommario_it, categoria, attendibilita,
                               fonte_nome, pubblicato_il
                          FROM ' . t('articles') . '
                         WHERE stato = \'pubblicato\' AND categoria = ?
                         ORDER BY pubblicato_il DESC LIMIT 40');
    $q->execute([$m[1]]);
    $html = render('lista', ['articoli' => $q->fetchAll(), 'intestazione' => ucfirst($m[1])],
        ['titolo' => ucfirst($m[1]) . ' — deftones.it']);
}

// --- tag
elseif (preg_match('#^/tag/(.+)$#', $percorso, $m)) {
    $tg = mb_substr($m[1], 0, 60);
    $q = $pdo->prepare('SELECT slug, titolo_it, sommario_it, categoria, attendibilita,
                               fonte_nome, pubblicato_il
                          FROM ' . t('articles') . '
                         WHERE stato = \'pubblicato\'
                           AND JSON_CONTAINS(tag, JSON_QUOTE(?))
                         ORDER BY pubblicato_il DESC LIMIT 40');
    $q->execute([$tg]);
    $html = render('lista', ['articoli' => $q->fetchAll(), 'intestazione' => '#' . $tg],
        ['titolo' => '#' . $tg . ' — deftones.it']);
}

// --- pannello
elseif (str_starts_with($percorso, '/admin')) {
    require $app . '/admin.php';
    exit;
}

else {
    pagina404();
}

// ---------------------------------------------------------------- uscita
if ($html !== null) {
    if ($cachabile) { cacheScrivi($percorso, $html); }
    header('X-Cache: miss');
    echo $html;
}
