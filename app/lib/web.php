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
