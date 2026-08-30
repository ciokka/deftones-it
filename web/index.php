<?php
/**
 * index.php — punto d'ingresso unico del sito.
 * Ogni richiesta passa di qui: .htaccess manda tutto a questo file.
 */
declare(strict_types=1);

// Dove sta l'applicazione. Sul server i file pubblici sono in
// public_html/ mentre il codice è in /home/bpdefton/deftones/app/, fuori
// dalla portata del web: le due cartelle NON sono affiancate, quindi il
// percorso è esplicito.
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
// La ricerca resta fuori dalla cache: la chiave è il percorso, e
// /cerca?q=milano e /cerca?q=chi hanno lo stesso percorso — si
// servirebbero i risultati l'uno dell'altro.
$cachabile = $metodo === 'GET' && !str_starts_with($percorso, '/cerca')
          && $percorso !== '/notizie'
          && !str_starts_with($percorso, '/admin') && !loggato();
// Le pagine non portavano nessuna intestazione di cache. Senza, ogni
// browser decide da sé quanto tenersele, e capita di guardare per
// mezz'ora una versione vecchia della pagina credendo che il sito sia
// rotto. La cache del server resta: questa dice solo al browser di
// chiedere sempre se è cambiato qualcosa.
if ($metodo === 'GET') { header('Cache-Control: no-cache, must-revalidate'); }

if ($cachabile && ($html = cacheLeggi($percorso)) !== null) {
    // La cache conserva il corpo della pagina, non le sue intestazioni:
    // il tipo di contenuto va rimesso a mano, altrimenti feed e sitemap
    // escono da qui dichiarati come HTML.
    if (str_ends_with($percorso, '.xml')) {
        header('Content-Type: ' . ($percorso === '/feed.xml'
            ? 'application/rss+xml' : 'application/xml') . '; charset=utf-8');
    }
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
                fonte_nome, pubblicato_il, rilevanza,
                immagine_url, immagine_origine,
                immagine_autore, immagine_licenza, immagine_licenza_url,
                immagine_fonte_url
           FROM ' . t('articles') . '
          WHERE stato = \'pubblicato\'
          ORDER BY pubblicato_il DESC LIMIT 21'
    )->fetchAll();

    $html = render('home', ['articoli' => $articoli], [
        'canonico'    => cfg('site_url') . u(''),
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

// --- sitemap: la mappa per i motori di ricerca
// Niente <priority> né <changefreq>: Google ha dichiarato di ignorarli, e
// una mappa piena di indicazioni che nessuno legge è solo più lunga.
// Restano l'indirizzo e la data dell'ultima modifica, che invece servono.
elseif ($percorso === '/sitemap.xml') {
    $sito = rtrim((string)cfg('site_url'), '/');

    $articoli = $pdo->query(
        'SELECT slug, pubblicato_il, aggiornato_il
           FROM ' . t('articles') . " 
          WHERE stato = 'pubblicato'
          ORDER BY pubblicato_il DESC"
    )->fetchAll();

    // Solo le raccolte che hanno almeno un articolo pubblicato: una
    // raccolta vuota è una pagina vuota, e non va segnalata a nessuno.
    $raccolte = $pdo->query(
        'SELECT t.slug, t.aggiornato_il
           FROM ' . t('temi') . " t
           JOIN " . t('articles') . " a
             ON a.tema_id = t.id AND a.stato = 'pubblicato'
          WHERE t.stato = 'pubblicato'
          GROUP BY t.id, t.slug, t.aggiornato_il
          ORDER BY t.slug"
    )->fetchAll();

    $dischi = $pdo->query('SELECT slug FROM ' . t('albums') . '
                            WHERE descrizione_it IS NOT NULL
                            ORDER BY ordine')->fetchAll();

    $categorie = $pdo->query(
        'SELECT categoria, MAX(pubblicato_il) AS ultima
           FROM ' . t('articles') . " 
          WHERE stato = 'pubblicato'
          GROUP BY categoria"
    )->fetchAll();

    $voci = [[u(''), $articoli[0]['pubblicato_il'] ?? null]];
    if ($raccolte) { $voci[] = [u('raccolte/'), $raccolte[0]['aggiornato_il']]; }
    $voci[] = [u('notizie'), $articoli[0]['pubblicato_il'] ?? null];
    $voci[] = [u('privacy'), null];
    if ($dischi)   { $voci[] = [u('discografia/'), null]; }
    foreach ($dischi as $x) { $voci[] = [u('discografia/' . $x['slug'] . '/'), null]; }
    foreach ($categorie as $c) { $voci[] = [u('categoria/' . $c['categoria'] . '/'), $c['ultima']]; }
    foreach ($raccolte as $r) { $voci[] = [u('raccolte/' . $r['slug'] . '/'), $r['aggiornato_il']]; }
    foreach ($articoli as $a) {
        $voci[] = [u('notizie/' . $a['slug'] . '/'), $a['aggiornato_il'] ?: $a['pubblicato_il']];
    }

    header('Content-Type: application/xml; charset=utf-8');
    $x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($voci as [$dove, $quando]) {
        $x .= '<url><loc>' . e($sito . $dove) . '</loc>';
        if ($quando) {
            $x .= '<lastmod>' . date('c', (int)strtotime((string)$quando)) . '</lastmod>';
        }
        $x .= "</url>\n";
    }
    $x .= "</urlset>\n";
    if ($cachabile) { cacheScrivi($percorso, $x); }
    echo $x;
    exit;
}

// --- privacy
elseif ($percorso === '/privacy') {
    $html = render('privacy', ['aggiornata' => '2026-08-30'], [
        'titolo'      => 'Privacy — deftones.it',
        'descrizione' => 'Questo sito non usa cookie e non carica niente da server '
                       . 'di altri. Cosa viene raccolto e cosa no.',
        'canonico'    => cfg('site_url') . u('privacy'),
    ]);
}

// --- archivio delle notizie, con filtri e pagine
elseif ($percorso === '/notizie') {
    $perPagina = 24;   // una costante non può stare dentro un ramo

    $cat  = (string)($_GET['cat'] ?? '');
    $anno = (int)($_GET['anno'] ?? 0);
    $pag  = max(1, (int)($_GET['p'] ?? 1));

    // La categoria è un ENUM: se arriva un valore che non c'è, si ignora
    // invece di finire in una query che non trova niente.
    $valide = array_column($pdo->query('SELECT DISTINCT categoria FROM ' . t('articles') . "
                                         WHERE stato = 'pubblicato'")->fetchAll(), 'categoria');
    if (!in_array($cat, $valide, true)) { $cat = ''; }
    if ($anno < 1990 || $anno > 2100)   { $anno = 0; }

    $dove = ["stato = 'pubblicato'"];
    $arg  = [];
    if ($cat)  { $dove[] = 'categoria = ?';            $arg[] = $cat; }
    if ($anno) { $dove[] = 'YEAR(pubblicato_il) = ?';  $arg[] = $anno; }
    $where = implode(' AND ', $dove);

    $st = $pdo->prepare('SELECT COUNT(*) FROM ' . t('articles') . " WHERE $where");
    $st->execute($arg);
    $totale = (int)$st->fetchColumn();

    $salto = ($pag - 1) * $perPagina;
    $st = $pdo->prepare('SELECT slug, titolo_it, sommario_it, categoria, attendibilita,
                                fonte_nome, pubblicato_il, rilevanza
                           FROM ' . t('articles') . "
                          WHERE $where
                          ORDER BY pubblicato_il DESC
                          LIMIT $perPagina OFFSET $salto");
    $st->execute($arg);
    $articoli = $st->fetchAll();

    // Il "carica altro" chiede la pagina dopo con frammento=1 e riceve
    // solo le schede: la stessa vista, senza il contorno.
    if (!empty($_GET['frammento'])) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo rendiParziale('schede', ['articoli' => $articoli]);
        exit;
    }

    $anni = $pdo->query('SELECT YEAR(pubblicato_il) AS anno, COUNT(*) AS quanti
                           FROM ' . t('articles') . "
                          WHERE stato = 'pubblicato' AND pubblicato_il IS NOT NULL
                          GROUP BY anno ORDER BY anno DESC")->fetchAll();
    $categorie = $pdo->query('SELECT categoria, COUNT(*) AS quanti
                                FROM ' . t('articles') . "
                               WHERE stato = 'pubblicato'
                               GROUP BY categoria ORDER BY quanti DESC")->fetchAll();

    $altra = null;
    if ($salto + count($articoli) < $totale) {
        $altra = u('notizie') . '?' . http_build_query(array_filter([
            'cat' => $cat, 'anno' => $anno ?: null, 'p' => $pag + 1,
        ]));
    }

    $titolo = 'Notizie' . ($cat ? ' — ' . ucfirst($cat) : '') . ($anno ? " — $anno" : '');
    $html = render('notizie', [
        'articoli' => $articoli, 'anni' => $anni, 'categorie' => $categorie,
        'cat' => $cat ?: null, 'anno' => $anno ?: null,
        'totale' => $totale, 'altraPagina' => $altra, 'perPagina' => $perPagina,
    ], [
        'titolo'      => $titolo . ' — deftones.it',
        'descrizione' => "Tutte le notizie sui Deftones pubblicate su deftones.it, "
                       . 'filtrabili per anno e categoria.',
        // Le pagine filtrate puntano tutte alla prima, senza filtri: sono
        // tagli dello stesso elenco, non pagine diverse da indicizzare.
        'canonico'    => cfg('site_url') . u('notizie'),
    ]);
}

// --- scheda notizia
elseif (preg_match('#^/notizie/([a-z0-9-]+)$#', $percorso, $m)) {
    $q = $pdo->prepare('SELECT * FROM ' . t('articles') . '
                         WHERE slug = ? AND stato = \'pubblicato\' LIMIT 1');
    $q->execute([$m[1]]);
    $a = $q->fetch();
    if (!$a) { pagina404(); }

    // La raccolta di appartenenza, se è pubblicata: dà al lettore un
    // modo di passare dall'articolo singolo alla storia intera.
    $raccolta = null;
    if (!empty($a['tema_id'])) {
        $q = $pdo->prepare('SELECT slug, titolo FROM ' . t('temi') . "
                             WHERE id = ? AND stato = 'pubblicato' LIMIT 1");
        $q->execute([$a['tema_id']]);
        $raccolta = $q->fetch() ?: null;
    }

    // Se l'articolo fa parte di una raccolta, "altre notizie" diventa
    // il resto di quella storia: è più utile di quattro titoli a caso.
    if ($raccolta) {
        $alt = $pdo->prepare('SELECT slug, titolo_it, pubblicato_il FROM ' . t('articles') . "
                               WHERE stato = 'pubblicato' AND tema_id = ? AND id <> ?
                               ORDER BY pubblicato_il DESC LIMIT 4");
        $alt->execute([$a['tema_id'], $a['id']]);
    } else {
        $alt = $pdo->prepare('SELECT slug, titolo_it, pubblicato_il FROM ' . t('articles') . "
                               WHERE stato = 'pubblicato' AND id <> ?
                               ORDER BY pubblicato_il DESC LIMIT 4");
        $alt->execute([$a['id']]);
    }

    $html = render('articolo', ['a' => $a, 'altri' => $alt->fetchAll(),
                                'raccolta' => $raccolta], [
        'titolo'      => $a['titolo_it'] . ' — deftones.it',
        'descrizione' => mb_substr($a['sommario_it'], 0, 160),
        'canonico'    => cfg('site_url') . u('notizie/' . $a['slug'] . '/'),
        // Quando c'è una foto vera, è quella che si vede quando il pezzo
        // viene condiviso. Le copertine generate restano fuori: sono SVG,
        // e nessun social sa disegnarle nell'anteprima.
    ] + ($a['immagine_url'] ? ['immagine' => cfg('site_url') . $a['immagine_url']] : []));
}

// --- categoria
elseif (preg_match('#^/categoria/([a-z]+)$#', $percorso, $m)) {
    $q = $pdo->prepare('SELECT slug, titolo_it, sommario_it, categoria, attendibilita,
                               fonte_nome, pubblicato_il, rilevanza
                          FROM ' . t('articles') . '
                         WHERE stato = \'pubblicato\' AND categoria = ?
                         ORDER BY pubblicato_il DESC LIMIT 40');
    $q->execute([$m[1]]);
    $html = render('lista', ['articoli' => $q->fetchAll(), 'intestazione' => ucfirst($m[1])],
        ['titolo'   => ucfirst($m[1]) . ' — deftones.it',
         'canonico' => cfg('site_url') . u('categoria/' . $m[1] . '/')]);
}

// --- tag
elseif (preg_match('#^/tag/(.+)$#', $percorso, $m)) {
    $tg = mb_substr($m[1], 0, 60);
    $q = $pdo->prepare('SELECT slug, titolo_it, sommario_it, categoria, attendibilita,
                               fonte_nome, pubblicato_il, rilevanza
                          FROM ' . t('articles') . '
                         WHERE stato = \'pubblicato\'
                           AND JSON_CONTAINS(tag, JSON_QUOTE(?))
                         ORDER BY pubblicato_il DESC LIMIT 40');
    $q->execute([$tg]);
    $html = render('lista', ['articoli' => $q->fetchAll(), 'intestazione' => '#' . $tg],
        ['titolo'   => '#' . $tg . ' — deftones.it',
         'canonico' => cfg('site_url') . u('tag/' . rawurlencode($tg) . '/')]);
}

// --- ricerca
elseif ($percorso === '/cerca') {
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
    $esito = $q === ''
        ? ['articoli' => [], 'errore' => null]
        : cercaArticoli($pdo, $q);

    $html = render('cerca', ['q' => $q] + $esito, [
        'titolo' => ($q !== '' ? '"' . $q . '" — ' : '') . 'Cerca su deftones.it',
    ]);
}

// --- suggerimenti mentre si scrive
// Niente HTML: solo i titoli che servono al menu a tendina. Sta qui e non
// in una pagina a parte perché è la stessa ricerca, con un limite più
// corto e senza il sommario.
elseif ($percorso === '/cerca.json') {
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
    $fuori = [];
    if ($q !== '') {
        foreach (cercaArticoli($pdo, $q, 8)['articoli'] as $a) {
            $fuori[] = [
                't' => (string)$a['titolo_it'],
                'u' => u('notizie/' . $a['slug'] . '/'),
                'd' => dataIt($a['pubblicato_il']),
                'c' => (string)$a['categoria'],
            ];
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo json_encode($fuori, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// --- indice delle raccolte
elseif ($percorso === '/raccolte') {
    $raccolte = $pdo->query(
        'SELECT t.slug, t.titolo, t.sottotitolo,
                COUNT(a.id) AS quanti
           FROM ' . t('temi') . " t
           LEFT JOIN " . t('articles') . " a
                  ON a.tema_id = t.id AND a.stato = 'pubblicato'
          WHERE t.stato = 'pubblicato'
          GROUP BY t.id
          HAVING quanti > 0
          ORDER BY t.ordine"
    )->fetchAll();

    $html = render('raccolte', ['raccolte' => $raccolte], [
        'titolo'      => 'Raccolte — deftones.it',
        'descrizione' => "Le storie che l'archivio di deftones.it ha raccontato a "
                       . 'puntate dal 2002, rimesse in ordine cronologico.',
        'canonico'    => cfg('site_url') . u('raccolte/'),
    ]);
}

// --- singola raccolta
elseif (preg_match('#^/raccolte/([a-z0-9-]+)$#', $percorso, $m)) {
    $q = $pdo->prepare('SELECT * FROM ' . t('temi') . "
                         WHERE slug = ? AND stato = 'pubblicato' LIMIT 1");
    $q->execute([$m[1]]);
    $r = $q->fetch();
    if (!$r) { pagina404(); }

    $q = $pdo->prepare('SELECT slug, titolo_it, pubblicato_il
                          FROM ' . t('articles') . "
                         WHERE tema_id = ? AND stato = 'pubblicato'
                         ORDER BY pubblicato_il ASC");
    $q->execute([$r['id']]);

    $html = render('raccolta', ['r' => $r, 'articoli' => $q->fetchAll()], [
        'titolo'      => $r['titolo'] . ' — deftones.it',
        'descrizione' => mb_substr((string)$r['sottotitolo'], 0, 160),
        'canonico'    => cfg('site_url') . u('raccolte/' . $r['slug'] . '/'),
    ]);
}

// --- discografia
elseif ($percorso === '/discografia') {
    $dischi = $pdo->query('SELECT slug, titolo, tipo, anno, copertina
                             FROM ' . t('albums') . '
                            ORDER BY ordine, anno')->fetchAll();

    $html = render('discografia', ['dischi' => $dischi], [
        'titolo'      => 'Discografia — deftones.it',
        'descrizione' => 'Tutti i dischi dei Deftones, dal 1995 a oggi: date, '
                       . 'etichette, tracklist e la storia di come sono nati.',
        'canonico'    => cfg('site_url') . u('discografia/'),
    ]);
}

elseif (preg_match('#^/discografia/([a-z0-9-]+)$#', $percorso, $m)) {
    $q = $pdo->prepare('SELECT * FROM ' . t('albums') . ' WHERE slug = ? LIMIT 1');
    $q->execute([$m[1]]);
    $d = $q->fetch();
    if (!$d) { pagina404(); }

    // Gli articoli che parlano di questo disco: il titolo compare nel
    // loro, che è lo stesso criterio con cui gli assegniamo la copertina.
    $q = $pdo->prepare('SELECT slug, titolo_it, pubblicato_il
                          FROM ' . t('articles') . "
                         WHERE stato = 'pubblicato' AND titolo_it LIKE ?
                         ORDER BY pubblicato_il DESC LIMIT 8");
    $q->execute(['%' . $d['titolo'] . '%']);
    $collegati = $q->fetchAll();

    $meta = [
        'titolo'      => $d['titolo'] . ' — Deftones — deftones.it',
        'descrizione' => mb_substr(trim(strip_tags((string)$d['descrizione_it'])), 0, 160)
                       ?: $d['titolo'] . ' dei Deftones' . ($d['anno'] ? ', ' . $d['anno'] : '') . '.',
        'canonico'    => cfg('site_url') . u('discografia/' . $d['slug'] . '/'),
    ];
    if ($d['copertina']) { $meta['immagine'] = cfg('site_url') . $d['copertina']; }

    $html = render('disco', ['d' => $d, 'collegati' => $collegati], $meta);
}

// --- vecchi indirizzi di WordPress: /GG-MM-AAAA/slug/
// Reindirizziamo solo se l'articolo esiste davvero: un 301 verso un 404
// è peggio di un 404 diretto, sia per chi naviga sia per i motori.
elseif (preg_match('#^/\d{2}-\d{2}-\d{4}/([a-z0-9-]+)$#', $percorso, $m)) {
    $q = $pdo->prepare('SELECT slug FROM ' . t('articles') . '
                         WHERE slug = ? AND stato = \'pubblicato\' LIMIT 1');
    $q->execute([$m[1]]);
    if ($q->fetchColumn() !== false) {
        header('Location: ' . u('notizie/' . $m[1] . '/'), true, 301);
        exit;
    }
    pagina404();
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
