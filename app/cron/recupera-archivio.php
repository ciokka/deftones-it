<?php
/**
 * recupera-archivio.php — riempie il buco fra il 2021 e l'agosto 2025.
 *
 *   php recupera-archivio.php --prova            conta e basta
 *   php recupera-archivio.php                    scopre, data, mette in coda
 *   php recupera-archivio.php --da=2021-01 --a=2025-08
 *   php recupera-archivio.php --limite=300       quante pagine aprire
 *   php recupera-archivio.php --solo=blabbermouth.net
 *
 * Perché non basta recupera-storico.php: quello chiede a Google News un
 * mese alla volta, e Google News non tiene l'archivio. Misurato: la
 * stessa ricerca senza date restituisce cento risultati, con
 * "after:2021-03-01 before:2021-04-01" ne restituisce zero. Non è un
 * problema di query, è che quell'indice indietro non va.
 *
 * La Wayback Machine invece sì, e ha un'API pubblica che elenca gli
 * indirizzi archiviati di un dominio. Serve solo a scoprirli: la data e
 * il titolo si prendono aprendo la pagina vera, che nella quasi
 * totalità dei casi è ancora online e porta og:title, og:description e
 * article:published_time. La data di cattura non serve a niente — un
 * articolo del 2010 può essere stato archiviato nel 2023, e infatti
 * succede di continuo.
 *
 * Gli indirizzi fuori periodo restano segnati come "troppo_vecchio", o
 * alla passata successiva li riapriremmo tutti da capo.
 *
 * Non chiama l'IA e non costa nulla: riempie la coda, e sarà enrich a
 * decidere cosa merita un articolo. Per un recupero di quattro anni
 * conviene alzare la soglia — vedi enrich.php --soglia=N.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

@set_time_limit(0);

/**
 * Dove cercare. Sono le testate che l'ingest già segue: se una notizia
 * del 2023 conta ancora qualcosa, è passata di qui.
 */
const DOMINI = [
    'blabbermouth.net',
    'theprp.com',
    'loudwire.com',
    'metalinjection.net',
    'consequence.net',
    'stereogum.com',
    'brooklynvegan.com',
    'revolvermag.com',
    'kerrang.com',
    'nme.com',
];

const CDX = 'https://web.archive.org/cdx/search/cdx';

$soloProva = in_array('--prova', $argv ?? [], true);
$da = '2021-01'; $a = '2025-08'; $limite = 200; $solo = null;
foreach ($argv ?? [] as $x) {
    if (preg_match('/^--da=(\d{4}-\d{2})$/', $x, $m))     { $da = $m[1]; }
    if (preg_match('/^--a=(\d{4}-\d{2})$/', $x, $m))      { $a  = $m[1]; }
    if (preg_match('/^--limite=(\d+)$/', $x, $m))         { $limite = max(1, (int)$m[1]); }
    if (preg_match('/^--solo=([a-z0-9.\-]+)$/i', $x, $m)) { $solo = strtolower($m[1]); }
}
$daData = $da . '-01';
$aData  = date('Y-m-t', strtotime($a . '-01'));

$lock = prendiLock('archivio');
if ($lock === false) { logline('Un altro recupero è in corso — esco.', 'archivio'); exit(0); }
function alog(string $m): void { logline($m, 'archivio'); }

$pdo = db();

alog(sprintf('%s — dal %s al %s, al massimo %d pagine',
    $soloProva ? 'PROVA (nessuna scrittura)' : 'Recupero archivio',
    $daData, $aData, $limite));

// --- la sorgente, spenta: l'ingest ordinario non deve toccarla -------
$q = $pdo->prepare('SELECT id FROM ' . t('sources') . ' WHERE nome = ?');
$q->execute(['Recupero archivio']);
$idSorgente = $q->fetchColumn();
if ($idSorgente === false) {
    if ($soloProva) {
        $idSorgente = 0;
    } else {
        $pdo->prepare('INSERT INTO ' . t('sources') . '
              (nome, url_feed, tipo, lingua, peso, filtra_keyword, attivo)
            VALUES (?,?,?,?,?,?,0)')
            ->execute(['Recupero archivio', 'https://web.archive.org/cdx/deftones',
                       'rss', 'en', 55, 1]);
        $idSorgente = (int)$pdo->lastInsertId();
        alog('  creata la sorgente "Recupero archivio" (spenta per l\'ingest ordinario)');
    }
}

/**
 * Gli indirizzi di un dominio che nominano i Deftones.
 *
 * Il filtro è sull'urlkey, cioè sull'indirizzo normalizzato: cerchiamo
 * gli articoli che hanno "deftones" nello slug, che sono quelli che
 * parlano di loro. Un pezzo che li nomina solo nel testo non lo
 * prendiamo — e va bene: qui interessa quello che gli è stato dedicato.
 */
function indirizziArchiviati(string $dominio): array
{
    $url = CDX . '?' . http_build_query([
        'url'       => $dominio,
        'matchType' => 'domain',
        'filter'    => 'urlkey:.*deftones.*',
        'from'      => '2021',
        'to'        => '2026',
        'collapse'  => 'urlkey',
        'fl'        => 'original',
        'limit'     => '3000',
        'output'    => 'text',
    ]);
    $r = httpGet($url, null, null, true, 90);
    if ($r['http'] !== 200 || $r['body'] === null) {
        alog(sprintf('  %s — la Wayback non risponde (http %d%s)',
            $dominio, $r['http'], $r['error'] ? ', ' . $r['error'] : ''));
        return [];
    }

    $fuori = [];
    foreach (explode("\n", $r['body']) as $riga) {
        $u = trim($riga);
        if ($u === '') { continue; }

        // La Wayback conserva anche gli indirizzi storti: quelli con la
        // pagina incollata due volte, i feed, le pagine di sfida
        // anti-bot, le stampe. Aprirli sarebbe tempo buttato.
        if (str_contains($u, 'sgcaptcha') || str_contains($u, '?')
            || preg_match('#/(feed|amp|print|page/\d+)/?$#i', $u)
            || substr_count($u, 'http') > 1) { continue; }
        if (!preg_match('#^https?://#i', $u)) { continue; }

        $fuori[canonicalizza($u)] = true;
    }
    return array_keys($fuori);
}

/**
 * Titolo, sommario e data di un articolo, letti dalla pagina vera.
 *
 * La data è l'unica cosa che conta davvero per decidere: quella di
 * cattura della Wayback non dice quando l'articolo è stato scritto, e
 * scambiarla per tale riempirebbe il sito di notizie del 2010.
 */
function schedaArticolo(string $url): ?array
{
    $r = httpGet($url, null, null, true, 25);
    if ($r['http'] !== 200 || $r['body'] === null) { return null; }
    $h = $r['body'];

    $meta = function (string $prop) use ($h): ?string {
        if (preg_match('#<meta[^>]+(?:property|name)=["\']' . preg_quote($prop, '#')
                     . '["\'][^>]+content=["\'](.*?)["\']#is', $h, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('#<meta[^>]+content=["\'](.*?)["\'][^>]+(?:property|name)=["\']'
                     . preg_quote($prop, '#') . '["\']#is', $h, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    };

    $data = $meta('article:published_time') ?: $meta('datePublished');
    if ($data === null && preg_match('#"datePublished"\s*:\s*"([^"]+)"#', $h, $m)) {
        $data = $m[1];
    }
    // Ultima spiaggia: certe testate mettono la data nell'indirizzo, e
    // theprp è fra queste.
    if ($data === null && preg_match('#/(\d{4})/(\d{2})/(\d{2})/#', $url, $m)) {
        $data = "$m[1]-$m[2]-$m[3]";
    }

    $titolo = $meta('og:title') ?: $meta('twitter:title');
    if ($titolo === null && preg_match('#<title[^>]*>(.*?)</title>#is', $h, $m)) {
        $titolo = trim(strip_tags($m[1]));
    }
    if ($titolo === null || $data === null) { return null; }

    $t = strtotime($data);
    if ($t === false) { return null; }

    return [
        'titolo'   => mb_substr(trim($titolo), 0, 500),
        'estratto' => mb_substr((string)($meta('og:description')
                                      ?: $meta('description') ?: ''), 0, 2000) ?: null,
        'editore'  => mb_substr((string)(parse_url($url, PHP_URL_HOST) ?: ''), 0, 120),
        'data'     => date('Y-m-d H:i:s', $t),
    ];
}

// ---------------------------------------------------------- il giro
$esiste   = $pdo->prepare('SELECT id FROM ' . t('raw_items') . ' WHERE src_url_hash = ? LIMIT 1');
$cercaDup = $pdo->prepare('SELECT id FROM ' . t('raw_items') . '
                            WHERE titolo_hash = ? AND stato <> \'duplicato\'
                            ORDER BY id ASC LIMIT 1');
$inserisci = $pdo->prepare('INSERT IGNORE INTO ' . t('raw_items') . '
       (source_id, url, url_canonico, src_url_hash, url_hash, titolo, titolo_hash,
        estratto, autore, editore, pubblicato_il, stato, duplicato_di)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');

$aperte = $nuovi = $dup = $fuoriPeriodo = $illeggibili = 0;

foreach (DOMINI as $dominio) {
    if ($solo !== null && $solo !== $dominio) { continue; }
    if ($aperte >= $limite) { break; }

    $trovati = indirizziArchiviati($dominio);
    $daAprire = [];
    foreach ($trovati as $u) {
        $esiste->execute([sha1($u)]);
        if ($esiste->fetchColumn() === false) { $daAprire[] = $u; }
    }
    alog(sprintf('  %-22s %4d indirizzi, %4d mai visti',
        $dominio, count($trovati), count($daAprire)));

    foreach ($daAprire as $u) {
        if ($aperte >= $limite) { break; }
        $aperte++;

        $s = schedaArticolo($u);
        usleep(700_000);        // educazione verso testate che non ci devono niente
        if ($s === null) { $illeggibili++; continue; }

        $dentro = $s['data'] >= $daData && $s['data'] <= $aData . ' 23:59:59';
        $conKeyword = contieneKeyword($s['titolo'] . ' ' . (string)$s['estratto']);

        // "troppo_vecchio" esiste dal 27 agosto e vuol dire esattamente
        // questo: pertinente, ma fuori dal periodo che ci interessa.
        // Avevo scritto un ALTER per aggiungere "scartato_data" prima di
        // accorgermene — un valore nuovo per un significato che c'era
        // già, e una migrazione da far girare per niente.
        if (!$dentro)            { $stato = 'troppo_vecchio';  $fuoriPeriodo++; }
        elseif (!$conKeyword)    { $stato = 'scartato_keyword'; }
        else {
            $cercaDup->execute([sha1(normalizzaTitolo($s['titolo']))]);
            $orig = $cercaDup->fetchColumn();
            if ($orig !== false) { $stato = 'duplicato'; $dup++; }
            else                 { $stato = 'nuovo';     $nuovi++; }
        }

        if ($soloProva) { continue; }

        $inserisci->execute([
            $idSorgente,
            mb_substr($u, 0, 1000), mb_substr($u, 0, 1000),
            sha1($u), sha1($u),
            $s['titolo'], sha1(normalizzaTitolo($s['titolo'])),
            $s['estratto'], null, $s['editore'], $s['data'],
            $stato,
            isset($orig) && $stato === 'duplicato' ? (int)$orig : null,
        ]);
    }
}

alog(sprintf('Fatto — %d pagine aperte: %d in coda, %d doppioni, '
           . '%d fuori periodo, %d illeggibili',
    $aperte, $nuovi, $dup, $fuoriPeriodo, $illeggibili));
if ($nuovi > 0 && !$soloProva) {
    alog('Ora tocca a enrich, e per quattro anni di arretrato conviene '
       . 'alzare la soglia: enrich.php --tutto --soglia=65');
}
if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
