<?php
/**
 * bootstrap.php — config, database, helper HTTP e di testo.
 * Nessuna dipendenza esterna: su questo hosting Composer non può girare
 * (proc_open è disabilitato), quindi tutto è PHP puro + cURL.
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Rome');

function cfg(?string $chiave = null): mixed
{
    static $c = null;
    if ($c === null) {
        $f = dirname(__DIR__) . '/config.php';
        if (!is_file($f)) {
            fwrite(STDERR, "config.php mancante — copia config.example.php e compilalo.\n");
            exit(1);
        }
        $c = require $f;
    }
    return $chiave === null ? $c : ($c[$chiave] ?? null);
}

/**
 * La connessione al database.
 *
 * Con $verifica = true controlla che sia ancora viva e la rifà se non lo
 * è. Serve dopo le attese lunghe: MySQL su hosting condiviso chiude le
 * connessioni inattive dopo pochi minuti, e uno script che aspetta la
 * risposta di un modello per cinque minuti al ritorno trova il cavo
 * staccato — "MySQL server has gone away".
 *
 * Attenzione: le query preparate appartengono alla connessione. Dopo una
 * riconnessione vanno ripreparate, altrimenti falliscono anche loro.
 */
function db(bool $verifica = false): PDO
{
    static $pdo = null;

    if ($pdo !== null && $verifica) {
        try {
            $pdo->query('SELECT 1');
        } catch (Throwable) {
            $pdo = null;
        }
    }

    if ($pdo === null) {
        $d = cfg('db');
        $pdo = new PDO(
            // La porta si dichiara solo dove non è quella standard: su
            // cPanel MySQL sta sulla 3306 e la voce in config.php non
            // esiste nemmeno, sul NAS di prova MariaDB 10 ascolta sulla
            // 3307. Senza la chiave il DSN resta identico a prima.
            "mysql:host={$d['host']};"
                . (empty($d['port']) ? '' : "port={$d['port']};")
                . "dbname={$d['name']};charset=utf8mb4",
            $d['user'],
            $d['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

/**
 * Il tipo di una colonna come lo scrive il database — `varchar(120)`,
 * `enum('a','b')` — oppure null se quella colonna non esiste.
 *
 * Serve a due mestieri: sapere se una colonna c'è già prima di
 * aggiungerla, e leggere i valori ammessi da una ENUM per riempirci un
 * menù a tendina senza doverli riscrivere a mano nel codice.
 *
 * SHOW COLUMNS e non information_schema: su questo hosting l'accesso a
 * information_schema dipende da quale utente MySQL sei — quello del
 * database ce l'ha, l'utente cPanel no. A SHOW basta il permesso di
 * lettura sulla tabella, che se sei qui hai già.
 *
 * Il nome della colonna finisce dentro la query invece che in un
 * segnaposto, e non è una svista: MariaDB non accetta parametri nelle
 * SHOW, e siccome le prepared qui sono native — EMULATE_PREPARES sta a
 * false, poche righe più su — un `SHOW COLUMNS ... LIKE ?` è un errore
 * di sintassi che arriva fino all'utente come una 500 muta. MySQL
 * invece lo accetta, quindi in produzione non si vedeva niente e il
 * guasto saltava fuori solo sull'ambiente di prova del NAS, dove la
 * modifica di un articolo non si apriva più.
 *
 * Il nome arriva sempre dal codice e mai da fuori: il controllo qui
 * sotto lo garantisce anche a chi passerà di qui domani.
 */
function colonnaTipo(PDO $pdo, string $tabella, string $colonna): ?string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $colonna)) { return null; }
    $r = $pdo->query('SHOW COLUMNS FROM `' . $tabella . '` LIKE ' . $pdo->quote($colonna))
             ->fetch();
    return $r === false ? null : (string)$r['Type'];
}

/** Nome tabella col prefisso configurato. */
function t(string $nome): string
{
    return cfg('db')['prefix'] . $nome;
}

/**
 * Scrive a video E su logs/<job>.log. Senza accesso SSH il file di log
 * è l'unico modo per vedere cosa ha fatto il cron: lo apri da
 * cPanel → Gestione file.
 */
function logline(string $msg, string $job = 'ingest'): void
{
    $riga = date('Y-m-d H:i:s') . '  ' . $msg;

    if (PHP_SAPI === 'cli') {
        echo $riga, "\n";
    } else {
        echo htmlspecialchars($riga), "<br>\n";
        @ob_flush();
        @flush();
    }

    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $f = $dir . '/' . $job . '.log';

    // rotazione grezza: oltre 2 MB si riparte, così il log non cresce a vuoto
    if (is_file($f) && filesize($f) > 2_097_152) { @rename($f, $f . '.1'); }
    @file_put_contents($f, $riga . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Come logline(), ma scrive anche su stderr. Il cron di cPanel manda una
 * mail solo se il comando produce output su stderr: usando questa per i
 * guasti veri (chiave scaduta, credito finito, API irraggiungibile) ricevi
 * una mail quando serve e silenzio quando tutto va bene.
 */
function allarme(string $msg, string $job = 'ingest'): void
{
    logline('GUASTO — ' . $msg, $job);
    if (PHP_SAPI === 'cli' && defined('STDERR')) {
        fwrite(STDERR, 'deftones.it [' . $job . '] ' . $msg . "\n");
    }
}

/**
 * Impedisce che due giri di cron si sovrappongano. Se il precedente è
 * ancora in corso questo esce subito invece di raddoppiare il lavoro.
 */
function prendiLock(string $job): mixed
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $fh = fopen($dir . '/' . $job . '.lock', 'c');
    if ($fh === false) { return null; }
    if (!flock($fh, LOCK_EX | LOCK_NB)) { fclose($fh); return false; }
    return $fh;
}

/**
 * Costo in euro di una chiamata, dalle tariffe di Claude Opus 5.
 * Sta qui e non nel client dell'API perché è aritmetica: la usa anche
 * il riepilogo, che con l'API non parla.
 */
function costoEuro(int $in, int $out): float
{
    return ($in / 1_000_000 * 5.00 + $out / 1_000_000 * 25.00) * 0.92;
}

// ---------------------------------------------------------------- HTTP

/**
 * GET con conditional request. Se il feed non è cambiato torna http=304
 * e non scarica nulla: risparmia banda e tempo su ogni giro di cron.
 *
 * @return array{http:int, body:?string, etag:?string, last_modified:?string, error:?string}
 */
function httpGet(string $url, ?string $etag = null, ?string $lastMod = null,
                 bool $seguiRedirect = true, ?int $attesaMax = null,
                 array $intestazioniExtra = []): array
{
    $intestazioni = ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*'];
    foreach ($intestazioniExtra as $x) { $intestazioni[] = $x; }
    if ($etag)    { $intestazioni[] = 'If-None-Match: ' . $etag; }
    if ($lastMod) { $intestazioni[] = 'If-Modified-Since: ' . $lastMod; }

    $risposta = ['etag' => null, 'last_modified' => null];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $seguiRedirect,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $attesaMax ?? cfg('http_timeout'),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT      => cfg('user_agent'),
        CURLOPT_ENCODING       => '',          // accetta gzip
        CURLOPT_HTTPHEADER     => $intestazioni,
        CURLOPT_HEADERFUNCTION => function ($ch, $riga) use (&$risposta) {
            $p = strpos($riga, ':');
            if ($p !== false) {
                $k = strtolower(trim(substr($riga, 0, $p)));
                $v = trim(substr($riga, $p + 1));
                if ($k === 'etag')          { $risposta['etag'] = $v; }
                if ($k === 'last-modified') { $risposta['last_modified'] = $v; }
            }
            return strlen($riga);
        },
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $finale = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $err  = curl_error($ch) ?: null;

    return [
        'http'          => $http,
        'body'          => is_string($body) ? $body : null,
        'url_finale'    => $finale,
        'etag'          => $risposta['etag'],
        'last_modified' => $risposta['last_modified'],
        'error'         => $err,
    ];
}

/** Risolve un redirect (Google News) senza scaricare il corpo della pagina. */
function risolviUrl(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 8,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_USERAGENT      => cfg('user_agent'),
        CURLOPT_NOBODY         => true,        // HEAD
    ]);
    curl_exec($ch);
    $finale = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    return $finale !== '' ? $finale : $url;
}

// ---------------------------------------------------------------- testo

/** Toglie i parametri di tracciamento e normalizza, per il dedup fra testate. */
function canonicalizza(string $url): string
{
    $p = parse_url($url);
    if (!$p || empty($p['host'])) { return $url; }

    $host = strtolower($p['host']);
    $host = preg_replace('/^www\./', '', $host);
    $path = rtrim($p['path'] ?? '/', '/');
    if ($path === '') { $path = '/'; }

    $query = '';
    if (!empty($p['query'])) {
        parse_str($p['query'], $q);
        foreach (array_keys($q) as $k) {
            if (preg_match('/^(utm_|fbclid|gclid|gbraid|wbraid|mc_|ref|referrer|source|igshid|_ga)/i', $k)) {
                unset($q[$k]);
            }
        }
        ksort($q);
        if ($q) { $query = '?' . http_build_query($q); }
    }
    // schema sempre https: la stessa pagina servita su http e https
    // deve produrre lo stesso hash, altrimenti il dedup la vede due volte
    return 'https://' . $host . $path . $query;
}

/**
 * Normalizza un titolo per il confronto: via il suffisso della testata,
 * via accenti e punteggiatura. Serve a riconoscere che
 * "Deftones Announce 2026 Tour - Loudwire" e
 * "Deftones announce 2026 tour | Kerrang!" sono la stessa notizia.
 */
function normalizzaTitolo(string $titolo): string
{
    $s = html_entity_decode($titolo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/\s+[-–—|]\s+[^-–—|]{2,40}$/u', '', $s);   // " - Nome Testata"
    $s = mb_strtolower($s, 'UTF-8');
    if (class_exists('Transliterator')) {
        $tr = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if ($tr) { $s = $tr->transliterate($s) ?: $s; }
    }
    $s = preg_replace('/[^a-z0-9\s]+/u', ' ', $s);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return $s;
}

function contieneKeyword(string $testo): bool
{
    $t = mb_strtolower($testo, 'UTF-8');
    foreach (cfg('keywords') as $k) {
        if (str_contains($t, mb_strtolower($k, 'UTF-8'))) { return true; }
    }
    return false;
}

function ripulisci(?string $html, int $max = 2000): ?string
{
    if ($html === null) { return null; }
    $s = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return $s === '' ? null : mb_substr($s, 0, $max);
}

// ---------------------------------------------------------------- feed

/**
 * Parser unico per RSS 2.0 e Atom.
 * @return list<array{titolo:string,url:string,estratto:?string,autore:?string,data:?string}>
 */
function parseFeed(string $xml): array
{
    $precedente = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($precedente);
    if ($doc === false) { return []; }

    $items = [];

    // --- RSS 2.0
    if (isset($doc->channel->item)) {
        foreach ($doc->channel->item as $it) {
            $dc = $it->children('http://purl.org/dc/elements/1.1/');
            $url = trim((string)$it->link);
            $tit = trim((string)$it->title);
            if ($url === '' || $tit === '') { continue; }
            $items[] = [
                'titolo'   => $tit,
                'url'      => $url,
                'estratto' => ripulisci((string)$it->description),
                'autore'   => trim((string)($it->author ?: $dc->creator)) ?: null,
                'data'     => trim((string)($it->pubDate ?: $dc->date)) ?: null,
                // Google News mette qui l'editore vero (NME, The Scotsman...):
                // è lui che va accreditato sul sito, non l'aggregatore
                'editore'  => trim((string)$it->source) ?: null,
            ];
        }
        return $items;
    }

    // --- Atom (Reddit, YouTube)
    if (isset($doc->entry)) {
        foreach ($doc->entry as $e) {
            $url = '';
            foreach ($e->link as $l) {
                $rel = (string)$l['rel'];
                if ($rel === '' || $rel === 'alternate') { $url = (string)$l['href']; break; }
            }
            $tit = trim((string)$e->title);
            if ($url === '' || $tit === '') { continue; }
            $items[] = [
                'titolo'   => $tit,
                'url'      => trim($url),
                'estratto' => ripulisci((string)($e->summary ?: $e->content)),
                'autore'   => trim((string)$e->author->name) ?: null,
                'data'     => trim((string)($e->published ?: $e->updated)) ?: null,
                'editore'  => null,
            ];
        }
    }
    return $items;
}

function aDatetime(?string $s): ?string
{
    if (!$s) { return null; }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/** Slug da un titolo: minuscolo, senza accenti, parole unite da trattini. */
function slug(string $testo, int $max = 80): string
{
    $s = html_entity_decode($testo, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (class_exists('Transliterator')) {
        $tr = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if ($tr) { $s = $tr->transliterate($s) ?: $s; }
    }
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    $s = trim((string)$s, '-');
    if (mb_strlen($s) > $max) {
        $s = mb_substr($s, 0, $max);
        $s = preg_replace('/-[^-]*$/', '', $s) ?: $s;   // non troncare a metà parola
    }
    return $s !== '' ? $s : 'notizia';
}

/**
 * Come slug(), ma garantisce che non esista già in df_articles.
 *
 * Verifica la connessione: viene chiamata al momento di salvare, che è
 * spesso subito dopo una lunga attesa sull'API — ed è proprio qui che il
 * "MySQL server has gone away" si è manifestato.
 */
function slugUnico(string $testo): string
{
    $base = slug($testo);
    $q = db(true)->prepare('SELECT 1 FROM ' . t('articles') . ' WHERE slug = ? LIMIT 1');
    $s = $base;
    for ($n = 2; $n < 100; $n++) {
        $q->execute([$s]);
        if ($q->fetchColumn() === false) { return $s; }
        $s = $base . '-' . $n;
    }
    return $base . '-' . substr(sha1($testo . microtime()), 0, 6);
}

/**
 * Nome leggibile dell'editore a partire da un URL, quando il feed non lo
 * dichiara: "https://www.nme.com/news/x" -> "nme.com".
 * Meglio del nome del feed, che è solo il postino.
 */
function editoreDaUrl(?string $url): ?string
{
    if (!$url) { return null; }
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) { return null; }
    $host = preg_replace('/^www\./i', '', strtolower($host));
    return ($host === '' || str_contains($host, 'news.google.com')) ? null : $host;
}

/**
 * Ripara le sequenze \uXXXX rimaste come testo letterale.
 * Capita che il modello raddoppi l'escape: json_decode restituisce
 * allora la stringa "già" invece di "già".
 */
function riparaEscape(?string $s): ?string
{
    if ($s === null || !str_contains($s, '\u')) { return $s; }
    return preg_replace_callback(
        '/\\\\u([0-9a-fA-F]{4})/',
        fn(array $m): string => mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE'),
        $s
    );
}

/**
 * Invia una mail in testo semplice e HTML insieme.
 *
 * Il mittente DEVE essere un indirizzo del dominio: scrivendo "da"
 * un indirizzo altrui (@me.com, @gmail.com) i controlli antispam del
 * destinatario vedono un server che non è autorizzato per quel dominio
 * e cestinano il messaggio.
 */
function inviaMail(string $a, string $oggetto, string $testo, string $html): bool
{
    $mittente = (string)(cfg('email_mittente') ?? '');
    if ($mittente === '' || $a === '') { return false; }

    $confine = 'x' . bin2hex(random_bytes(12));
    $intestazioni = implode("\r\n", [
        'From: ' . cfg('site_name') . ' <' . $mittente . '>',
        'Reply-To: ' . $mittente,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $confine . '"',
        'X-Auto-Response-Suppress: All',      // niente risposte automatiche
        'Auto-Submitted: auto-generated',     // è posta di servizio, non personale
    ]);

    $corpo = "--$confine\r\n"
           . "Content-Type: text/plain; charset=UTF-8\r\n"
           . "Content-Transfer-Encoding: 8bit\r\n\r\n"
           . $testo . "\r\n\r\n"
           . "--$confine\r\n"
           . "Content-Type: text/html; charset=UTF-8\r\n"
           . "Content-Transfer-Encoding: 8bit\r\n\r\n"
           . $html . "\r\n\r\n"
           . "--$confine--\r\n";

    return @mail($a, mb_encode_mimeheader($oggetto, 'UTF-8'), $corpo, $intestazioni);
}
