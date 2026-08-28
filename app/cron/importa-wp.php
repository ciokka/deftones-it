<?php
/**
 * importa-wp.php — migrazione dell'archivio WordPress (2002-2021).
 *
 *   php importa-wp.php --analisi     non scrive nulla: dice cosa c'è
 *   php importa-wp.php               importa in df_articles come bozze
 *
 * Gli articoli entrano tutti come 'draft'. Nessuno va online senza che
 * tu lo approvi: sono vent'anni di materiale e alcune scelte (i testi
 * delle canzoni, le traduzioni di interviste altrui) sono tue.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/web.php';

@set_time_limit(0);
$soloAnalisi = in_array('--analisi', $argv ?? [], true);
$pdo = db();
function ilog(string $m): void { logline($m, 'importa'); }

// ---------------------------------------------------------------- pulizia

/**
 * Da HTML del 2005 a HTML leggibile: via i FONT, via le classi di un tema
 * che non esiste più, tag in minuscolo, niente attributi di stile.
 */
function ripuliscHtml(string $html): string
{
    $s = $html;

    // --- video: recupero prima di distruggere ---------------------------
    // Gli <object>/<embed> sono Flash, morto nel 2020, e resterebbero come
    // markup rotto. Ma contengono l'ID del video di YouTube: lo estraiamo
    // e lo rimettiamo come iframe moderno, così il video torna a funzionare.
    $s = preg_replace_callback(
        '#<object\b.*?</object>#is',
        function (array $m): string {
            if (preg_match('#youtube(?:-nocookie)?\.com/(?:v|embed)/([A-Za-z0-9_-]{6,})#i', $m[0], $v)) {
                return '<p><iframe src="https://www.youtube-nocookie.com/embed/' . $v[1]
                     . '" title="Video YouTube" loading="lazy" allowfullscreen></iframe></p>';
            }
            return '';                       // Flash non recuperabile: via
        },
        $s
    );
    $s = preg_replace('#<(?:embed|param)\b[^>]*>#i', '', $s);

    // iframe: teniamo solo YouTube. Gli altri sono servizi chiusi da anni
    // (o incorporamenti di terzi che non vogliamo caricare sulle pagine).
    $s = preg_replace_callback(
        '#<iframe\b[^>]*src=["\']?([^"\'\s>]+)[^>]*>(?:\s*</iframe>)?#i',
        function (array $m): string {
            if (preg_match('#youtube(?:-nocookie)?\.com/(?:embed/|v/)([A-Za-z0-9_-]{6,})#i', $m[1], $v)
             || preg_match('#youtu\.be/([A-Za-z0-9_-]{6,})#i', $m[1], $v)) {
                return '<iframe src="https://www.youtube-nocookie.com/embed/' . $v[1]
                     . '" title="Video YouTube" loading="lazy" allowfullscreen></iframe>';
            }
            return '';
        },
        $s
    );

    // --- immagini morte -------------------------------------------------
    // TinyPic ha chiuso nel 2019: quelle immagini non esistono più. Meglio
    // toglierle che lasciare 98 icone rotte sparse nell'archivio.
    $s = preg_replace('#<img[^>]+src=["\']?https?://[^"\'\s>]*(?:tinypic\.com|photobucket\.com|imageshack\.us)[^>]*>#i', '', $s);

    // <FONT ...>testo</FONT> → testo. Il tag è deprecato da HTML 4.
    $s = preg_replace('#</?font[^>]*>#i', '', $s);
    // <span>/<div> vuoti di significato usati solo per lo stile del tema
    $s = preg_replace('#</?(?:span|div|center)[^>]*>#i', '', $s);
    // attributi di presentazione ovunque
    $s = preg_replace('#\s(?:class|style|align|width|height|border|cellpadding|cellspacing|bgcolor|color|face|size)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $s);
    // tag in minuscolo
    $s = preg_replace_callback('#</?([A-Z][A-Z0-9]*)#', fn($m) => str_replace($m[1], strtolower($m[1]), $m[0]), $s);
    // <br> multipli = stacco di paragrafo
    $s = preg_replace('#(?:\s*<br\s*/?>\s*){2,}#i', "</p>\n<p>", $s);
    $s = preg_replace('#<br\s*/?>#i', "<br>\n", $s);
    // paragrafi vuoti lasciati dalle sostituzioni
    $s = preg_replace('#<p>(?:\s|&nbsp;|<br>)*</p>#i', '', $s);
    $s = preg_replace('#\n{3,}#', "\n\n", $s);

    return trim($s);
}

/** Le immagini passano da wp-content/uploads a /media, dove le copieremo. */
function rimappaImmagini(string $html): string
{
    return preg_replace(
        '#(https?://(?:www\.)?deftones\.it)?/wp-content/uploads/#i',
        '/media/', $html
    );
}

/**
 * I link interni puntano ai vecchi indirizzi /GG-MM-AAAA/slug/: li
 * riscriviamo sui nuovi. Senza questo, ogni rimando fra un articolo e
 * l'altro dell'archivio finirebbe su un 404.
 */
function rimappaLinkInterni(string $html): string
{
    return preg_replace(
        '#https?://(?:www\.)?deftones\.it/\d{2}-\d{2}-\d{4}/([a-z0-9-]+)/?#i',
        '/notizie/$1/', $html
    );
}

/** L'indirizzo che l'articolo aveva sul vecchio sito. */
function urlVecchio(string $data, string $nome): string
{
    $t = strtotime($data);
    return $t ? '/' . date('d-m-Y', $t) . '/' . $nome . '/' : '';
}

// ---------------------------------------------------------------- lettura
$post = $pdo->query(
    "SELECT p.ID, p.post_date, p.post_title, p.post_name, p.post_content, p.post_excerpt
       FROM wp_posts p
      WHERE p.post_type = 'post' AND p.post_status = 'publish'
      ORDER BY p.post_date"
)->fetchAll();

ilog(sprintf('%s — %d articoli pubblicati nell\'archivio',
    $soloAnalisi ? 'ANALISI (nessuna scrittura)' : 'Importazione', count($post)));

// tag e categorie, in una query sola
$tassonomie = [];
foreach ($pdo->query(
    "SELECT tr.object_id, t.name, tt.taxonomy
       FROM wp_term_relationships tr
       JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
       JOIN wp_terms t ON t.term_id = tt.term_id
      WHERE tt.taxonomy IN ('category','post_tag')") as $r) {
    $tassonomie[(int)$r['object_id']][$r['taxonomy']][] = $r['name'];
}

// ---------------------------------------------------------------- analisi
if ($soloAnalisi) {
    $perAnno = $tag = $conImg = $srcImg = $sospettiTesti = $vuoti = [];
    $collisioni = [];
    $slugEsistenti = array_flip($pdo->query('SELECT slug FROM ' . t('articles'))->fetchAll(PDO::FETCH_COLUMN));

    foreach ($post as $p) {
        $anno = substr((string)$p['post_date'], 0, 4);
        $perAnno[$anno] = ($perAnno[$anno] ?? 0) + 1;

        $c = (string)$p['post_content'];
        if (preg_match_all('#<(\w+)#', $c, $m)) {
            foreach ($m[1] as $tg) { $tag[strtolower($tg)] = ($tag[strtolower($tg)] ?? 0) + 1; }
        }
        if (stripos($c, '<img') !== false) {
            $conImg[] = $p['ID'];
            if (preg_match_all('#<img[^>]+src=["\']?([^"\'\s>]+)#i', $c, $mm)) {
                foreach ($mm[1] as $src) {
                    $host = parse_url($src, PHP_URL_HOST) ?: '(relativo)';
                    $srcImg[$host] = ($srcImg[$host] ?? 0) + 1;
                }
            }
        }
        if (mb_strlen(strip_tags($c)) < 200) { $vuoti[] = $p['ID']; }

        // titoli che fanno pensare a testi di canzoni: sono opere protette
        if (preg_match('#\b(le parole di|testo|testi|lyrics|traduzione)\b#iu', (string)$p['post_title'])) {
            $sospettiTesti[] = ['id' => $p['ID'], 'titolo' => $p['post_title']];
        }
        if (isset($slugEsistenti[$p['post_name']])) { $collisioni[] = $p['post_name']; }
    }

    ksort($perAnno);
    ilog('');
    ilog('--- articoli per anno ---');
    $riga = '';
    foreach ($perAnno as $a => $n) {
        $riga .= sprintf('%s:%-4d ', $a, $n);
        if (mb_strlen($riga) > 66) { ilog('  ' . $riga); $riga = ''; }
    }
    if ($riga) { ilog('  ' . $riga); }

    arsort($tag);
    ilog('');
    ilog('--- tag HTML presenti nei contenuti ---');
    ilog('  ' . implode('  ', array_map(fn($k, $v) => "$k:$v",
        array_keys(array_slice($tag, 0, 18)), array_slice($tag, 0, 18))));

    ilog('');
    ilog(sprintf('--- immagini: %d articoli ne contengono ---', count($conImg)));
    arsort($srcImg);
    foreach (array_slice($srcImg, 0, 8, true) as $h => $n) { ilog(sprintf('  %-38s %d', $h, $n)); }

    ilog('');
    ilog(sprintf('--- articoli quasi vuoti (meno di 200 caratteri): %d ---', count($vuoti)));
    ilog(sprintf('--- slug già usati dalle notizie nuove: %d ---', count($collisioni)));

    ilog('');
    ilog(sprintf('--- possibili testi di canzoni o traduzioni: %d ---', count($sospettiTesti)));
    ilog('    (sono opere protette: vanno decisi uno per uno, non pubblicati in blocco)');
    foreach (array_slice($sospettiTesti, 0, 20) as $s) {
        ilog(sprintf('  %-6d %s', $s['id'], mb_substr($s['titolo'], 0, 62)));
    }
    if (count($sospettiTesti) > 20) { ilog(sprintf('  … e altri %d', count($sospettiTesti) - 20)); }

    ilog('');
    ilog('Nessuna scrittura effettuata. Togli --analisi per importare.');
    exit(0);
}

// ------------------------------------------------------------ importazione
$pdo->exec('ALTER TABLE ' . t('articles') . '
  ADD COLUMN IF NOT EXISTS url_vecchio VARCHAR(300) NULL COMMENT \'indirizzo sul vecchio WordPress\',
  ADD COLUMN IF NOT EXISTS wp_id BIGINT UNSIGNED NULL COMMENT \'ID originale in wp_posts\'');
$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_art_wp ON ' . t('articles') . ' (wp_id)');

$ins = $pdo->prepare(
    'INSERT INTO ' . t('articles') . '
       (slug, titolo_it, sommario_it, corpo_it, categoria, tag, rilevanza,
        attendibilita, stato, pubblicato_il, creato_il, url_vecchio, wp_id)
     VALUES (?,?,?,?,\'evergreen\',?,50,\'confermato\',\'draft\',?,?,?,?)
     ON DUPLICATE KEY UPDATE
       titolo_it = VALUES(titolo_it), corpo_it = VALUES(corpo_it),
       tag = VALUES(tag), url_vecchio = VALUES(url_vecchio)'
);

$fatti = $saltati = 0;
foreach ($post as $p) {
    $corpo = rimappaLinkInterni(rimappaImmagini(ripuliscHtml((string)$p['post_content'])));

    // Un articolo può essere breve ma contenere un video: quello è
    // contenuto, non un vuoto. Scartiamo solo ciò che non ha né l'uno
    // né l'altro.
    $haVideo = str_contains($corpo, '<iframe');
    if (!$haVideo && mb_strlen(strip_tags($corpo)) < 120) { $saltati++; continue; }

    $tag = $tassonomie[(int)$p['ID']]['post_tag'] ?? [];
    $sommario = (string)$p['post_excerpt'];
    if (trim($sommario) === '') {
        $sommario = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($corpo))), 0, 300);
    }

    $slug = $p['post_name'] !== '' ? $p['post_name'] : slug((string)$p['post_title']);
    $ins->execute([
        mb_substr($slug, 0, 200),
        mb_substr(html_entity_decode((string)$p['post_title'], ENT_QUOTES, 'UTF-8'), 0, 300),
        $sommario,
        $corpo,
        json_encode(array_values($tag), JSON_UNESCAPED_UNICODE),
        $p['post_date'],
        $p['post_date'],
        urlVecchio((string)$p['post_date'], (string)$p['post_name']),
        (int)$p['ID'],
    ]);
    $fatti++;
}

ilog(sprintf('Importati %d articoli come bozze, %d saltati perché vuoti.', $fatti, $saltati));
ilog('Nessuno è online: vanno approvati dal pannello.');
