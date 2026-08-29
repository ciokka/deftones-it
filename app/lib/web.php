<?php
/**
 * web.php — quel poco che serve per servire pagine: escaping, URL,
 * cache su file, rendering dei template.
 */
declare(strict_types=1);

/** Escape per l'HTML. Da usare SEMPRE sui dati che vengono dal database. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL assoluto a partire dalla radice del sito. */
function u(string $percorso = ''): string
{
    $base = rtrim((string)(cfg('base_url') ?? ''), '/');
    return $base . '/' . ltrim($percorso, '/');
}

/** Data in italiano: "28 agosto 2026". */
function dataIt(?string $sql): string
{
    if (!$sql) { return ''; }
    $mesi = [1=>'gennaio','febbraio','marzo','aprile','maggio','giugno',
             'luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $t = strtotime($sql);
    return $t ? date('j', $t) . ' ' . $mesi[(int)date('n', $t)] . ' ' . date('Y', $t) : '';
}

/** "3 ore fa", "ieri", "5 giorni fa" — per le notizie recenti. */
function quandoIt(?string $sql): string
{
    if (!$sql) { return ''; }
    $t = strtotime($sql);
    if (!$t) { return ''; }
    $d = time() - $t;
    if ($d < 3600)  { $m = max(1, (int)($d / 60)); return "$m minut" . ($m === 1 ? 'o' : 'i') . ' fa'; }
    if ($d < 86400) { $h = (int)($d / 3600);       return "$h or" . ($h === 1 ? 'a' : 'e') . ' fa'; }
    if ($d < 172800) { return 'ieri'; }
    if ($d < 604800) { return (int)($d / 86400) . ' giorni fa'; }
    return dataIt($sql);
}

/** Rende un template dentro il layout e restituisce l'HTML. */
function render(string $vista, array $dati = [], array $meta = []): string
{
    extract($dati, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/views/' . $vista . '.php';
    $contenuto = (string)ob_get_clean();

    $titolo      = $meta['titolo']      ?? cfg('site_name');
    $descrizione = $meta['descrizione'] ?? '';
    $canonico    = $meta['canonico']    ?? '';
    // L'anteprima per la condivisione. Le pagine possono passarne una
    // propria; in mancanza vale quella del sito.
    $immagine    = $meta['immagine']    ?? cfg('site_url') . u('assets/og.png');

    ob_start();
    require dirname(__DIR__) . '/views/layout.php';
    return (string)ob_get_clean();
}

// ---------------------------------------------------------------- cache
//
// Il sito è in sola lettura per chi lo visita: la stessa pagina vale per
// tutti. La salviamo come file e la riserviamo finché non pubblichi
// qualcosa. Così le pagine costano al server quanto un file statico.

function cachePercorso(string $chiave): string
{
    $dir = dirname(__DIR__) . '/cache';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir . '/' . sha1($chiave) . '.html';
}

function cacheLeggi(string $chiave): ?string
{
    $f = cachePercorso($chiave);
    $ttl = (int)(cfg('cache_ttl') ?? 900);
    if (is_file($f) && (time() - filemtime($f)) < $ttl) {
        $c = @file_get_contents($f);
        return $c === false ? null : $c;
    }
    return null;
}

function cacheScrivi(string $chiave, string $html): void
{
    @file_put_contents(cachePercorso($chiave), $html, LOCK_EX);
}

/** Svuota la cache. Da chiamare a ogni pubblicazione. */
function cacheSvuota(): int
{
    $n = 0;
    foreach (glob(dirname(__DIR__) . '/cache/*.html') ?: [] as $f) {
        if (@unlink($f)) { $n++; }
    }
    return $n;
}

// ---------------------------------------------------------------- admin

function sessioneAvvia(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function loggato(): bool
{
    // Non avviare una sessione per chi non ne ha già una: altrimenti ogni
    // visitatore anonimo si porta a casa un cookie che non gli serve, e il
    // sito avrebbe bisogno di un banner per una cosa che non usa.
    if (session_status() === PHP_SESSION_NONE && empty($_COOKIE[session_name()])) {
        return false;
    }
    sessioneAvvia();
    return !empty($_SESSION['admin_id']);
}

/** Token anti-CSRF: senza, chiunque potrebbe farti pubblicare da fuori. */
function csrf(): string
{
    sessioneAvvia();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfValido(?string $t): bool
{
    sessioneAvvia();
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function vaiA(string $percorso): never
{
    header('Location: ' . u($percorso), true, 302);
    exit;
}

function pagina404(): never
{
    http_response_code(404);
    echo render('errore', ['codice' => 404,
        'messaggio' => 'La pagina che cerchi non esiste.'], ['titolo' => 'Pagina non trovata']);
    exit;
}

/**
 * Il sigillo delle cose nostre.
 *
 * Un articolo senza fonte esterna non è il riassunto di nessuno: o è
 * stato commissionato dal pannello, o viene dall'archivio del vecchio
 * sito. In entrambi i casi il testo è nostro, ed è giusto dirlo dove
 * altrimenti resterebbe scritto "fonte:" seguito dal vuoto.
 */
function bollino(bool $esteso = false): string
{
    $svg = '<svg viewBox="0 0 16 16" width="' . ($esteso ? 17 : 15) . '" height="'
         . ($esteso ? 17 : 15) . '" fill="none" stroke="currentColor" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path stroke-width=".9" d="M8.00 1.10L9.68 2.29L11.73 2.20L12.50 4.10'
         . 'L14.28 5.13L13.89 7.15L14.83 8.98L13.41 10.47L13.21 12.52L11.22 13.01'
         . 'L9.94 14.62L8.00 13.95L6.06 14.62L4.78 13.01L2.79 12.52L2.59 10.47'
         . 'L1.17 8.98L2.11 7.15L1.72 5.13L3.50 4.10L4.27 2.20L6.32 2.29Z"/>'
         . '<path stroke-width="1.3" d="M5.4 8.2 7.1 9.9 10.7 6"/></svg>';

    return '<span class="bollino">' . $svg . '<span>esclusiva deftones.it</span></span>'
         . ($esteso ? ' <span class="bollino-nota">— scritta per questo sito</span>' : '');
}

/**
 * Il pulsante di condivisione delle schede: uno solo, muto, che diventa
 * il pannello di sistema dove il browser lo espone e la copia
 * dell'indirizzo dove non lo espone. Vedi lo script in layout.php.
 */
function condividiMini(string $slug, string $titolo): string
{
    $svg = '<svg viewBox="0 0 16 16" width="15" height="15" fill="none" '
         . 'stroke="currentColor" stroke-width="1.15" stroke-linecap="round" '
         . 'aria-hidden="true">'
         . '<circle cx="12.2" cy="3.6" r="1.85"/>'
         . '<circle cx="12.2" cy="12.4" r="1.85"/>'
         . '<circle cx="4.2" cy="8" r="1.85"/>'
         . '<path d="M5.82 7.11 10.58 4.49M5.82 8.89 10.58 11.51"/></svg>';

    return '<button class="condividi-mini" type="button" aria-label="condividi"'
         . ' data-url="' . e(cfg('site_url') . u('notizie/' . $slug . '/')) . '"'
         . ' data-titolo="' . e($titolo) . '">' . $svg . '</button>';
}
