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
/**
 * Una vista sola, senza testata né piede.
 *
 * render() avvolge sempre quello che produce dentro layout.php, ed è
 * giusto così per una pagina. Ma il "carica altro" chiede solo le schede
 * da aggiungere in fondo a una pagina che c'è già: passandogli render()
 * si riceveva indietro il sito intero, logo e menu compresi, e finivano
 * appiccicati in mezzo all'elenco.
 */
function rendiParziale(string $vista, array $dati = []): string
{
    extract($dati, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/views/' . $vista . '.php';
    return (string)ob_get_clean();
}

function render(string $vista, array $dati = [], array $meta = []): string
{
    extract($dati, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/views/' . $vista . '.php';
    $contenuto = (string)ob_get_clean();

    $titolo      = $meta['titolo']      ?? cfg('site_name');
    $descrizione = $meta['descrizione'] ?? '';
    $canonico    = $meta['canonico']    ?? '';
    // L'anteprima per la condivisione. Le pagine ne passano una propria
    // come percorso — /media/copertine/xxx.jpg — e qui diventa indirizzo
    // assoluto; in mancanza vale quella del sito.
    $imgNostra   = !isset($meta['immagine']);
    $imgPercorso = $meta['immagine'] ?? u('assets/og.png');
    $immagine    = cfg('site_url') . $imgPercorso;
    $immagineAlt = $meta['immagine_alt']
        ?? ($imgNostra ? 'deftones.it — the italian Deftones family' : null);

    // Le misure dichiarate devono essere quelle vere. Prima erano scritte
    // a mano 1200x675 per qualunque immagine: giuste per la nostra og.png,
    // sbagliate per una fotografia scaricata da Commons, che di suo è
    // 1280x850 o qualunque altra cosa. Un social che si fida di una misura
    // sbagliata ritaglia l'anteprima male, o la rifiuta.
    $misure = $imgNostra
        ? [1200, 675]                       // og.png l'abbiamo fatta noi
        : misuraImmagine($imgPercorso);

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

/**
 * La copertina di un articolo.
 *
 * Due casi: una foto vera salvata sul nostro disco, oppure niente. Le
 * foto arrivano da Commons con proporzioni tutte diverse: il ritaglio a
 * 16:9 lo fa il CSS, così una verticale non sfonda la pagina e la fascia
 * resta sempre la stessa.
 */
function copertina(array $a, bool $subito = false): string
{
    $img = copertinaImg($a, $subito);
    if ($img === '') { return ''; }
    return '<figure class="copertina">' . $img . creditoImmagine($a) . '</figure>';
}

/**
 * Solo l'immagine, senza cornice né credito. Il banner della home li
 * dispone per conto suo, perché lì il credito va sopra la foto e non
 * sotto. Vuoto se l'articolo non ha una copertina: chi la usa deve
 * saperlo gestire, e la home lo fa disattivando il banner.
 */
function copertinaImg(array $a, bool $subito = false): string
{
    // Nessun ripiego: un articolo senza fotografia vera non ha copertina.
    // Un'immagine generata riempie lo spazio ma non dice niente, e messa
    // accanto a una foto vera si vede subito che è un tappabuchi.
    if (empty($a['immagine_url'])) { return ''; }

    return '<img src="' . e((string)$a['immagine_url']) . '" alt=""'
         . ' loading="' . ($subito ? 'eager' : 'lazy') . '" decoding="async">';
}

/**
 * Il credito sotto la foto.
 *
 * Non è una cortesia: una foto CC BY è libera *a condizione* che
 * l'autore sia citato. Senza questa riga non stiamo usando una foto
 * libera, stiamo usando una foto altrui. Per questo il credito sta
 * sotto l'immagine e non in fondo alla pagina.
 */
function creditoImmagine(array $a, string $tag = 'figcaption'): string
{
    $o = (string)($a['immagine_origine'] ?? '');
    // 'generata' non si produce più, ma qualche riga vecchia può ancora
    // averlo: senza questo controllo mostrerebbe un credito vuoto.
    if ($o === '' || $o === 'generata') { return ''; }

    if ($o === 'disco') {
        $t = e((string)($a['immagine_licenza'] ?? ''));
        return $t === '' ? '' : "<$tag class=\"credito\">" . $t . "</$tag>";
    }

    $link = function (?string $url, string $testo): string {
        return $url
            ? '<a href="' . e($url) . '" target="_blank" rel="noopener">' . $testo . '</a>'
            : $testo;
    };

    $pezzi = [];
    $autore = trim((string)($a['immagine_autore'] ?? ''));
    $pezzi[] = $autore !== ''
        ? 'foto di ' . $link($a['immagine_fonte_url'] ?? null, e(mb_substr($autore, 0, 120)))
        : $link($a['immagine_fonte_url'] ?? null, 'foto da Wikimedia Commons');

    $lic = trim((string)($a['immagine_licenza'] ?? ''));
    if ($lic !== '') {
        $pezzi[] = $link($a['immagine_licenza_url'] ?? null, e($lic));
    }

    return "<$tag class=\"credito\">" . implode(' · ', $pezzi) . "</$tag>";
}

/**
 * La ricerca a testo pieno, usata sia dalla pagina sia dai suggerimenti.
 *
 * Modalità booleana con +parola*: tutte le parole devono esserci —
 * altrimenti "deftones milano" restituisce mezzo sito — e ognuna vale
 * anche come prefisso, così "chitarr" trova chitarre e chitarrista.
 *
 * La stringa viene spezzata sui caratteri non alfanumerici, il che si
 * porta via anche gli operatori booleani: + - * " ( ) ~ < > @ hanno un
 * significato in questa modalità, e una parentesi storta farebbe fallire
 * la query invece di cercare.
 *
 * Torna ['articoli' => [...], 'errore' => string|null].
 */
function cercaArticoli(PDO $pdo, string $q, int $limite = 60): array
{
    $parole = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    // Sotto le tre lettere MySQL non indicizza affatto: cercare "chi" va
    // bene, "di" non troverebbe niente e sembrerebbe un guasto.
    $parole = array_slice(array_filter($parole, fn($p) => mb_strlen($p) >= 3), 0, 6);
    if (!$parole) {
        return ['articoli' => [], 'errore' => 'Cerca una parola di almeno tre lettere.'];
    }

    $espressione = implode(' ', array_map(fn($p) => '+' . $p . '*', $parole));
    $limite = max(1, min(60, $limite));

    $campi = 'slug, titolo_it, sommario_it, categoria, attendibilita,
              fonte_nome, pubblicato_il, rilevanza';

    // Il primo indice comprende il corpo dell'articolo; se non è ancora
    // stato creato si ripiega su quello dello schema iniziale, che c'è
    // di sicuro. Meglio trovare meno che dare errore.
    foreach ([
        'titolo_it, sommario_it, corpo_it',
        'titolo_it, sommario_it',
    ] as $colonne) {
        try {
            $st = $pdo->prepare(
                "SELECT $campi, MATCH($colonne) AGAINST (? IN BOOLEAN MODE) AS punti
                   FROM " . t('articles') . "
                  WHERE stato = 'pubblicato'
                    AND MATCH($colonne) AGAINST (? IN BOOLEAN MODE)
                  ORDER BY punti DESC, pubblicato_il DESC
                  LIMIT $limite");
            $st->execute([$espressione, $espressione]);
            return ['articoli' => $st->fetchAll(), 'errore' => null];
        } catch (Throwable $e) {
            $ultimo = $e;
        }
    }
    logline('Ricerca fallita: ' . $ultimo->getMessage(), 'web');
    return ['articoli' => [], 'errore' => 'La ricerca non è disponibile in questo momento.'];
}

/**
 * Da quale punteggio una notizia è "hot".
 *
 * La rilevanza la assegna il modello quando scrive il pezzo, da 0 a 100.
 * Ottanta è la soglia sopra la quale una notizia non è solo pertinente
 * ma conta davvero: un disco nuovo, un tour annunciato, una perdita.
 * Se il bollo comincia a comparire ovunque perde il suo senso — è un
 * numero solo, si alza qui.
 */
const HOT_DA = 70;

/** Il bollo, con la fiammella. Vuoto quando non serve. */
function etichettaHot(mixed $rilevanza): string
{
    if (!is_numeric($rilevanza) || (int)$rilevanza < HOT_DA) { return ''; }

    return '<span class="hot" title="notizia di rilievo">'
         . '<svg viewBox="0 0 16 16" width="11" height="11" fill="currentColor" aria-hidden="true">'
         . '<path d="M8 1.5c2.5 2.5 4.4 4.1 4.4 6.9a4.4 4.4 0 0 1-8.8 0'
         . 'c0-1.5.7-2.7 1.8-3.7.1 1 .5 1.7 1.1 2.1C6.2 5.2 6.7 3.2 8 1.5Z"/>'
         . '</svg>hot</span>';
}

/**
 * I video incorporati diventano una facciata: si caricano al clic.
 *
 * Quarantacinque articoli dell'archivio contengono cento iframe di
 * YouTube. Anche nella versione "nocookie", un iframe contatta i server
 * di Google appena la pagina si apre, e gli manda l'indirizzo IP di chi
 * legge — che è esattamente il trattamento su cui si sono perse le cause
 * dei Google Fonts. Un sito senza banner con dentro un iframe di YouTube
 * non è un sito senza banner.
 *
 * Al posto dell'iframe mettiamo quindi un riquadro nostro, che non
 * chiede niente a nessuno. Chi vuole vedere il video ci clicca, e in quel
 * momento — con un gesto suo, consapevole — il video arriva.
 *
 * La trasformazione avviene al momento di mostrare la pagina e non nel
 * database: gli articoli restano come sono stati importati, e il giorno
 * che questa scelta cambia si cambia qui.
 */
function facciateVideo(string $html): string
{
    $fatto = preg_replace_callback(
        '#<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*</iframe>#i',
        function (array $m): string {
            $url = $m[1];
            if (!preg_match('#(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{6,24})#i',
                            $url, $v)) {
                // Non è YouTube: un collegamento è più onesto di un
                // riquadro che carica qualcosa che non sappiamo cos'è.
                return '<p class="video-altrove"><a href="' . e($url) . '" target="_blank"'
                     . ' rel="noopener">apri il contenuto incorporato</a></p>';
            }

            return '<div class="video" data-video="' . e($v[1]) . '">'
                 . '<button type="button" class="video-avvia">'
                 . '<span class="video-play" aria-hidden="true">'
                 . '<svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor">'
                 . '<path d="M8.4 5.6 19 12 8.4 18.4Z"/></svg></span>'
                 . '<span class="video-nota">guarda il video'
                 . '<em>arriva da YouTube, e solo se lo chiedi tu</em></span>'
                 . '</button></div>';
        },
        $html
    );
    return $fatto ?? $html;
}

/**
 * Larghezza e altezza di un'immagine che stiamo servendo noi.
 *
 * Solo quelle sotto /media/, che sono le uniche di cui conosciamo il
 * posto sul disco. getimagesize legge la sola intestazione del file, e
 * la pagina che la usa finisce comunque in cache: si paga una volta.
 * Se non si riesce a misurare si torna null, e le misure non vengono
 * dichiarate affatto — meglio tacere che dire un numero sbagliato.
 */
function misuraImmagine(string $percorso): ?array
{
    if (!str_starts_with($percorso, '/media/')) { return null; }
    $base = rtrim((string)(cfg('media_dir') ?: ''), '/');
    if ($base === '') { return null; }
    $file = $base . substr($percorso, strlen('/media'));
    if (!is_file($file)) { return null; }
    $d = @getimagesize($file);
    return $d ? [(int)$d[0], (int)$d[1]] : null;
}

