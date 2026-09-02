<?php
/**
 * copertine.php — da dove vengono le immagini, e a quali condizioni.
 *
 * La regola che governa tutto questo file sta nello schema del database
 * fin dal primo giorno: "solo immagini nostre o con licenza — mai foto
 * delle testate". Le foto che accompagnano gli articoli dei giornali
 * sono di agenzia o dei fotografi accreditati: prenderle è quello che
 * fanno quasi tutti gli aggregatori, ed è anche il motivo per cui ogni
 * tanto a qualcuno arriva una richiesta di danni.
 *
 * Quindi si pesca in tre posti, in quest'ordine:
 *
 *   1. la copertina del disco, quando l'articolo parla di un disco;
 *   2. una foto di Wikimedia Commons con licenza libera.
 *
 * Se non si trova né l'una né l'altra, l'articolo resta senza copertina.
 * Non c'è un ripiego: un'immagine generata riempirebbe lo spazio senza
 * dire niente, e messa accanto a una foto vera si vedrebbe subito che è
 * un tappabuchi.
 */
declare(strict_types=1);

const COMMONS_API = 'https://commons.wikimedia.org/w/api.php';

/**
 * Le categorie di Commons da cui peschiamo, e il soggetto che
 * rappresentano. Sono categorie e non ricerche a testo libero per un
 * motivo pratico: cercare "Chino Moreno" su Commons restituisce
 * diciannovemila file, quasi tutti di altre persone che si chiamano
 * così. La categoria contiene solo lui.
 *
 * Abe Cunningham, Frank Delgado e Chi Cheng non hanno una categoria
 * propria: gli articoli che li riguardano ripiegano sulle foto di
 * gruppo, che è meglio di una foto sbagliata.
 */
const COMMONS_CATEGORIE = [
    'Deftones'          => 'band',
    'Chino Moreno'      => 'chino',
    'Stephen Carpenter' => 'stephen',
    'Sergio Vega'       => 'sergio',
];

/** I nomi che, trovati nel titolo o nei tag, indirizzano la scelta. */
const NOMI_SOGGETTO = [
    'chino moreno'      => 'chino',
    'chino'             => 'chino',
    'stephen carpenter' => 'stephen',
    'stef carpenter'    => 'stephen',
    'sergio vega'       => 'sergio',
    // Su Commons questi tre non hanno una categoria propria, ma su
    // Flickr le loro foto ci sono: se il catalogo non ne ha, la scelta
    // ripiega da sola sulle foto di gruppo.
    'abe cunningham'    => 'abe',
    'frank delgado'     => 'frank',
    'chi cheng'         => 'chi',
];

// ---------------------------------------------------------------- API

/** Una chiamata all'API di Commons. Torna l'array decodificato o null. */
function commonsApi(array $parametri): ?array
{
    $parametri += ['format' => 'json', 'formatversion' => '2'];
    $r = httpGet(COMMONS_API . '?' . http_build_query($parametri));
    if ($r['http'] !== 200 || $r['body'] === null) { return null; }
    $d = json_decode($r['body'], true);
    return is_array($d) ? $d : null;
}

/** I file dentro una categoria. Solo i file, non le sottocategorie. */
function commonsFileDi(string $categoria): array
{
    $d = commonsApi([
        'action'   => 'query',
        'list'     => 'categorymembers',
        'cmtitle'  => 'Category:' . $categoria,
        'cmtype'   => 'file',
        'cmlimit'  => '500',
    ]);
    $out = [];
    foreach ($d['query']['categorymembers'] ?? [] as $m) {
        $out[] = (string)$m['title'];
    }
    return $out;
}

/**
 * I file che nominano i Deftones ma che nessuno ha ancora categorizzato.
 *
 * Le categorie sono il posto giusto dove cercare, ma ci si finisce solo
 * se qualcuno ce li mette: fra caricare una fotografia e vederla
 * ordinata in Category:Deftones passano mesi, e a volte non passa mai.
 * Le foto recenti stanno quasi tutte in quel limbo — "Deftones en
 * spectacle au Centre Bell en 2025", caricata a marzo, non è in nessuna
 * delle diciassette categorie che visitiamo.
 *
 * Perciò si fa anche una ricerca a testo libero sui nomi dei file, dai
 * più recenti. Il rumore c'è — la ricerca di Commons trova "deftones"
 * dentro registri parlamentari del Seicento — e si toglie qui: il nome
 * del file deve nominare il gruppo o uno dei suoi. Chi resta è nostro.
 */
function commonsRecenti(int $quanti = 120): array
{
    $d = commonsApi([
        'action'      => 'query',
        'list'        => 'search',
        'srsearch'    => 'deftones',
        'srnamespace' => '6',            // solo i file
        'srlimit'     => (string)min($quanti, 500),
        'srsort'      => 'create_timestamp_desc',
    ]);

    $out = [];
    foreach ($d['query']['search'] ?? [] as $r) {
        $t = (string)$r['title'];
        $min = mb_strtolower($t);
        // "Deftone Stylus typeface sample" non è una fotografia della
        // band: la s finale non è un dettaglio.
        if (!str_contains($min, 'deftones')) {
            $suo = false;
            foreach (array_keys(NOMI_SOGGETTO) as $nome) {
                if (str_contains($min, $nome)) { $suo = true; break; }
            }
            if (!$suo) { continue; }
        }
        $out[] = $t;
    }
    return $out;
}

/** Le sottocategorie di una categoria: un livello, non tutto l'albero. */
function commonsSottocategorie(string $categoria): array
{
    $d = commonsApi([
        'action'  => 'query',
        'list'    => 'categorymembers',
        'cmtitle' => 'Category:' . $categoria,
        'cmtype'  => 'subcat',
        'cmlimit' => '100',
    ]);
    $out = [];
    foreach ($d['query']['categorymembers'] ?? [] as $m) {
        $t = (string)$m['title'];
        // Le foto dei logo non servono come copertina: sono ritagli di
        // marchio, non immagini della band.
        if (str_contains(mb_strtolower($t), 'logo')) { continue; }
        $out[] = substr($t, strlen('Category:'));
    }
    return $out;
}

/**
 * I metadati di un gruppo di file. Commons ne accetta cinquanta per
 * volta, e restituisce già la miniatura alla larghezza che chiediamo:
 * scarichiamo 1200px invece dell'originale da venti megabyte, e non
 * serve saper ridimensionare niente sul server.
 */
function commonsMetadati(array $titoli): array
{
    $out = [];
    foreach (array_chunk($titoli, 50) as $lotto) {
        $d = commonsApi([
            'action'    => 'query',
            'titles'    => implode('|', $lotto),
            'prop'      => 'imageinfo',
            'iiprop'    => 'url|size|extmetadata|mime',
            'iiurlwidth' => '1200',
        ]);
        foreach ($d['query']['pages'] ?? [] as $p) {
            $ii = $p['imageinfo'][0] ?? null;
            if (!$ii) { continue; }
            $md = $ii['extmetadata'] ?? [];
            $val = fn(string $k): string => (string)($md[$k]['value'] ?? '');
            $out[] = [
                'commons'     => (string)$p['title'],
                'mime'        => (string)($ii['mime'] ?? ''),
                'url_file'    => (string)($ii['thumburl'] ?? $ii['url'] ?? ''),
                'url_pagina'  => (string)($ii['descriptionurl'] ?? ''),
                'larghezza'   => (int)($ii['thumbwidth'] ?? $ii['width'] ?? 0),
                'altezza'     => (int)($ii['thumbheight'] ?? $ii['height'] ?? 0),
                'l_orig'      => (int)($ii['width'] ?? 0),
                'a_orig'      => (int)($ii['height'] ?? 0),
                'data'        => dataFoto($val('DateTimeOriginal') ?: $val('DateTime')),
                // Su Commons il nome del file è già una didascalia.
                'titolo'      => mb_substr(preg_replace('/\.[a-z]{3,4}$/i', '',
                                    substr((string)$p['title'], strlen('File:'))) ?? '', 0, 200),
                'autore'      => testoSemplice($val('Artist')),
                'licenza'     => testoSemplice($val('LicenseShortName')),
                'licenza_url' => $val('LicenseUrl'),
                'vincoli'     => testoSemplice($val('Restrictions')),
            ];
        }
        usleep(200000);   // Commons non chiede di rallentare, ma è educato
    }
    return $out;
}

/**
 * Quando è stata scattata una fotografia.
 *
 * Commons risponde in ordine sparso: "2016-10-28 01:32", "8 October
 * 2016", "2016", o un frammento di HTML con dentro un <time>. Si prende
 * quello che si riesce a leggere e si tiene com'è — anno e mese se il
 * giorno non c'è, il solo anno se non c'è altro. Una data parziale è
 * un'informazione; una data completata a caso è una bugia.
 */
function dataFoto(string $grezzo): ?string
{
    $t = testoSemplice($grezzo);
    if ($t === '') { return null; }
    if (preg_match('/(19|20)\d{2}-\d{2}-\d{2}/', $t, $m)) { return $m[0]; }
    if (preg_match('/(19|20)\d{2}-\d{2}(?!\d)/', $t, $m)) { return $m[0]; }
    if (preg_match('/\b((?:19|20)\d{2})\b/', $t, $m))     { return $m[1]; }
    return null;
}

/** I metadati arrivano con dentro dell'HTML: qui resta solo il testo. */
function testoSemplice(string $html): string
{
    $t = preg_replace('#<br\s*/?>#i', ' ', $html);
    $t = strip_tags((string)$t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
}

/**
 * Una licenza è utilizzabile se ci dice sì. Nel dubbio, no: un file con
 * la licenza scritta in modo che non riconosciamo resta fuori, perché
 * il costo di sbagliare non è simmetrico.
 */
function licenzaLibera(string $licenza): bool
{
    $l = mb_strtolower(trim($licenza));
    if ($l === '') { return false; }

    // Le ND si scartano sempre, e non è una scelta di prudenza: le
    // copertine vengono ritagliate a 16:9, e un ritaglio è un'opera
    // derivata. Usarle sarebbe fuori licenza a prescindere da tutto.
    if (preg_match('/\bnd\b|no[\s-]?deriv/i', $l)) { return false; }

    // Le NC dipendono da una scelta scritta in config.php. Il sito non
    // è commerciale e non lo diventerà — ma se un giorno ci comparisse
    // un banner, un link affiliato o una raccolta fondi, quella riga va
    // rimessa a false e il catalogo ripulito. È qui apposta perché sia
    // una riga sola da cambiare, e non una caccia nel codice.
    if (!cfg('foto_non_commerciali')
        && preg_match('/\bnc\b|non[\s-]?commercial/i', $l)) {
        return false;
    }
    foreach (['fair use', 'non-free', 'nonfree'] as $no) {
        if (str_contains($l, $no)) { return false; }
    }
    return str_starts_with($l, 'cc')
        || str_contains($l, 'public domain')
        || str_contains($l, 'pubblico dominio')   // come la scriviamo noi per Flickr
        || $l === 'attribution';
}

/** Vale la pena tenerla come copertina? */
function immagineAdatta(array $i): bool
{
    if (!in_array($i['mime'], ['image/jpeg', 'image/png'], true)) { return false; }
    if (!licenzaLibera($i['licenza'])) { return false; }
    // Il campo "Restrictions" di Commons non parla di copyright: la
    // licenza è già stata verificata sopra. Segnala altro.
    //
    // "personality" è il diritto all'immagine di chi è ritratto, e
    // Commons lo appiccica a qualunque foto di persona riconoscibile —
    // sessanta delle nostre. Riguarda l'uso pubblicitario: mettere la
    // faccia di qualcuno su un manifesto per vendere qualcosa. Un
    // articolo che parla di quella persona è esattamente l'uso che quel
    // diritto non ostacola, quindi passa.
    //
    // "trademarked" e "insignia" invece sì: sono marchi e stemmi, e un
    // marchio non si usa come illustrazione senza pensarci.
    foreach (['trademark', 'insignia', 'currency'] as $no) {
        if (str_contains(mb_strtolower($i['vincoli']), $no)) { return false; }
    }
    // Qui si decide solo se una fotografia vale la pena di stare in
    // catalogo, non se è adatta a fare da copertina automatica: sono due
    // domande diverse, e per un pezzo scritto a mano la seconda la fa
    // una persona guardando le miniature.
    //
    // I verticali entrano, quindi: ritagliati a 16:9 diventano quasi
    // sempre un mento, e infatti l'assegnazione automatica non li
    // prende — ma dentro un articolo, scelti da chi l'ha scritto, sono
    // spesso i più belli. Prima li buttavamo al momento della raccolta,
    // che è il posto sbagliato per buttare qualcosa: da lì non tornano
    // indietro se non rifacendo il giro.
    //
    // Il metro è il lato lungo: novecento pixel bastano per una
    // miniatura, per un ritratto in mezzo al testo e per capire cosa si
    // sta guardando.
    return max($i['l_orig'], $i['a_orig']) >= 900;
}

// ------------------------------------------------------- disco e foto

/** La copertina ufficiale di un disco, dal Cover Art Archive. */
function copertinaDisco(string $mbid): ?string
{
    $url = 'https://coverartarchive.org/release-group/' . $mbid . '/front-1200';
    $r = httpGet($url);
    if ($r['http'] === 200 && $r['body'] !== null && strlen($r['body']) > 8000) {
        return $r['body'];
    }
    return null;
}

/**
 * Di cosa parla l'articolo, ai fini dell'immagine. Legge il titolo e i
 * tag che il modello ha già scritto: nessuna chiamata alle API, quindi
 * si può rifare quante volte si vuole senza spendere niente.
 */
function soggettoArticolo(string $titolo, array $tag, array $dischi): array
{
    $solotitolo = mb_strtolower($titolo);

    // I dischi per primi, ma cercati SOLO nel titolo.
    //
    // La prima versione guardava anche nei tag, e sbagliava di brutto:
    // i tag sono i temi dell'articolo, non il suo argomento. "Terry Date
    // Drums: expansion per SSD4" si prendeva la copertina di White Pony
    // perché era taggato white pony; "25 Best Albums of 2020" quella di
    // Ohms; un'intervista a Sergio quella di Koi No Yokan. Nel titolo
    // invece il nome del disco c'è quando l'articolo parla di quello.
    // Quando un titolo nomina due dischi — "Intervista a Sergio: Eros,
    // etichetta, White Pony" — vince quello nominato per primo, non il
    // più lungo: l'argomento di un pezzo si annuncia all'inizio.
    $vincitore = null; $prima = PHP_INT_MAX;
    foreach ($dischi as $d) {
        $t = mb_strtolower((string)$d['titolo']);
        // "Deftones" e "Covers" come titoli sono troppo generici: il
        // primo compare in ogni articolo, il secondo in mezzi.
        if (mb_strlen($t) < 4 || in_array($t, ['deftones', 'covers'], true)) { continue; }
        if (preg_match('/\b' . preg_quote($t, '/') . '\b/u', $solotitolo, $m, PREG_OFFSET_CAPTURE)) {
            if ($m[0][1] < $prima) { $prima = $m[0][1]; $vincitore = $d; }
        }
    }
    if ($vincitore !== null) {
        return ['tipo' => 'disco', 'chiave' => (string)$vincitore['mbid'],
                'nome' => (string)$vincitore['titolo']];
    }

    // Per le persone invece i tag vanno bene: se un articolo è taggato
    // "chino moreno" una foto di Chino ci sta, qualunque cosa racconti.
    $testo = $solotitolo . ' ' . mb_strtolower(implode(' ', $tag));
    foreach (NOMI_SOGGETTO as $nome => $soggetto) {
        if (str_contains($testo, $nome)) {
            return ['tipo' => 'foto', 'chiave' => $soggetto, 'nome' => $nome];
        }
    }

    return ['tipo' => 'foto', 'chiave' => 'band', 'nome' => 'la band'];
}

// =====================================================================
//  Assegnare una copertina
// =====================================================================

/**
 * Oltre quante volte la stessa busta di un disco non si riusa.
 * Senza un tetto la home diventa un muro della stessa immagine.
 */
const MAX_PER_DISCO = 4;

/** Dove finiscono i file scaricati, sul disco. */
function cartellaCopertine(): string
{
    return rtrim((string)(cfg('media_dir') ?: '/home/bpdefton/public_html/media'), '/')
         . '/copertine';
}

/**
 * Assegna una copertina a un articolo e la scrive nel database.
 *
 * Sta qui e non dentro il cron perché la usano in due: il giro
 * automatico ogni quattro ore, e il pulsante "pubblica con copertina" del
 * pannello, che la vuole subito su un articolo solo. Due copie della
 * stessa logica sarebbero divergute — è già successo tre volte in questo
 * progetto, e ogni volta il sintomo è comparso da tutt'altra parte.
 *
 * $a deve contenere almeno id, slug, titolo_it, tag.
 * Torna ['origine' => 'disco'|'commons'|null, 'nota' => string].
 */
function assegnaCopertina(PDO $pdo, array $a, array $dischi, bool $prova = false): array
{
    // Le copertine dei dischi si scaricano una volta sola per processo:
    // venti articoli su 'private music' chiederebbero venti volte lo
    // stesso file al Cover Art Archive.
    static $copertineDisco = [];
    // In prova i contatori del database non avanzano: senza questo ogni
    // articolo ripescherebbe la stessa foto, e il giro a vuoto mostrerebbe
    // una monotonia che nella realtà non c'è.
    static $giaScelte = [];

    $cartella = cartellaCopertine();
    // Il cron controlla la cartella e si ferma se manca; qui no, perché
    // chiamata dal pannello questa funzione deve solo fare del suo meglio.
    if (!$prova && !is_dir($cartella)) { @mkdir($cartella, 0755, true); }
    $file = $cartella . '/' . $a['slug'] . '.jpg';
    $tag = json_decode((string)($a['tag'] ?? ''), true) ?: [];
    $s = soggettoArticolo((string)$a['titolo_it'], $tag, $dischi);
    $esito = null;

    // --- 1. la copertina del disco, se il titolo nomina un disco ------
    if ($s['tipo'] === 'disco' && $s['chiave'] !== '') {
        $rif = 'https://musicbrainz.org/release-group/' . $s['chiave'];
        $q = $pdo->prepare('SELECT COUNT(*) FROM ' . t('articles') . "
                             WHERE immagine_origine = 'disco' AND immagine_fonte_url = ?");
        $q->execute([$rif]);

        if ((int)$q->fetchColumn() < MAX_PER_DISCO) {
            if (!array_key_exists($s['chiave'], $copertineDisco)) {
                $copertineDisco[$s['chiave']] = copertinaDisco((string)$s['chiave']);
            }
            $dati = $copertineDisco[$s['chiave']];
            if ($dati !== null && ($prova || @file_put_contents($file, $dati) !== false)) {
                $esito = [
                    'url' => '/media/copertine/' . $a['slug'] . '.jpg',
                    'autore' => null, 'licenza' => 'copertina di ' . $s['nome'],
                    'licenza_url' => null, 'fonte' => $rif,
                    'origine' => 'disco', 'img' => null, 'nota' => $s['nome'],
                ];
            }
        }
    }

    // --- 2. una foto libera dal catalogo ------------------------------
    if ($esito === null) {
        $soggetto = $s['tipo'] === 'foto' ? $s['chiave'] : 'band';
        // Il soggetto giusto ha la precedenza solo finché le sue foto sono
        // state usate meno di tre volte: di Stephen Carpenter ce ne sono
        // quattro, e senza quel limite ogni articolo che lo nomina se le
        // riprenderebbe in giro all'infinito mentre centoventisei foto di
        // gruppo mai usate stanno lì.
        // RAND() alla fine perché gli id sono raggruppati per concerto:
        // ordinando per id, articoli vicini prenderebbero foto della
        // stessa sera, tutte uguali fra loro.
        $fuori = $giaScelte ? ' AND id NOT IN (' . implode(',', $giaScelte) . ')' : '';
        $q = $pdo->prepare('SELECT * FROM ' . t('immagini') . "
                             WHERE scartata = 0 AND soggetto IN (?, ?) $fuori
                               -- Solo orizzontali abbastanza larghe: il
                               -- ritaglio a 16:9 è automatico e nessuno
                               -- lo guarda prima che sia pubblicato.
                               AND larghezza >= altezza AND larghezza >= 800
                             ORDER BY (soggetto = ? AND usata < 3) DESC, usata ASC, RAND()
                             LIMIT 1");
        $q->execute([$soggetto, 'band', $soggetto]);
        $img = $q->fetch();

        if ($img) {
            $dati = $prova ? 'x' : (httpGet((string)$img['url_file'])['body'] ?? null);
            if ($dati !== null && ($prova || strlen($dati) > 5000)) {
                if ($prova || @file_put_contents($file, $dati) !== false) {
                    if ($prova) { $giaScelte[] = (int)$img['id']; }
                    $esito = [
                        'url' => '/media/copertine/' . $a['slug'] . '.jpg',
                        'autore' => $img['autore'], 'licenza' => $img['licenza'],
                        'licenza_url' => $img['licenza_url'], 'fonte' => $img['url_pagina'],
                        'origine' => 'commons', 'img' => (int)$img['id'],
                        'nota' => (string)$img['autore'],
                    ];
                }
            }
        }
    }

    // --- 3. niente. Nessun ripiego: si annota solo che si è cercato. ---
    if ($esito === null) {
        $esito = ['url' => null, 'autore' => null, 'licenza' => null,
                  'licenza_url' => null, 'fonte' => null,
                  'origine' => null, 'img' => null, 'nota' => ''];
    }

    if (!$prova) {
        $pdo->prepare('UPDATE ' . t('articles') . '
             SET immagine_url = ?, immagine_autore = ?, immagine_licenza = ?,
                 immagine_licenza_url = ?, immagine_fonte_url = ?,
                 immagine_origine = ?, immagine_cercata_il = NOW()
           WHERE id = ?')->execute([
            $esito['url'], $esito['autore'], $esito['licenza'], $esito['licenza_url'],
            $esito['fonte'], $esito['origine'], $a['id'],
        ]);
        if ($esito['img'] !== null) {
            $pdo->prepare('UPDATE ' . t('immagini') . '
                 SET usata = usata + 1 WHERE id = ?')->execute([$esito['img']]);
        }
    }

    return ['origine' => $esito['origine'], 'nota' => (string)$esito['nota']];
}

/** I dischi con un mbid, dal più lungo di titolo: serve a soggettoArticolo(). */
function dischiPerTitolo(PDO $pdo): array
{
    return $pdo->query('SELECT titolo, mbid FROM ' . t('albums') . '
                         WHERE mbid IS NOT NULL
                         ORDER BY CHAR_LENGTH(titolo) DESC')->fetchAll();
}

/**
 * La miniatura di una fotografia del catalogo.
 *
 * Nel pannello se ne mostrano centinaia insieme: chiederle a piena
 * misura sarebbe qualche decina di megabyte per aprire una pagina.
 * Entrambi gli archivi servono formati più piccoli cambiando il nome
 * del file, quindi non serve generare né conservare niente.
 */
function miniaturaFoto(string $url): string
{
    // Commons: .../thumb/x/xx/Nome.jpg/1200px-Nome.jpg
    if (str_contains($url, '/1200px-')) {
        return str_replace('/1200px-', '/320px-', $url);
    }
    // Flickr: il suffisso prima dell'estensione dice la misura.
    // _b è 1024, _z è 640, _n è 320.
    if (preg_match('#^(https://live\.staticflickr\.com/.+)_[a-z]\.jpg$#i', $url, $m)) {
        return $m[1] . '_n.jpg';
    }
    return $url;
}


// ---------------------------------------------- raccolte dal pannello

/**
 * Il percorso del PHP da riga di comando.
 *
 * Non è quello che sta girando: il web gira sotto un binario diverso,
 * spesso senza le funzioni che servono e con un tempo massimo di
 * esecuzione da pagina web. Su cPanel il CLI sta in un posto preciso,
 * che si può cambiare da config.php se un giorno cambia versione.
 */
function phpCli(): string
{
    return (string)(cfg('php_cli') ?: '/opt/cpanel/ea-php83/root/usr/bin/php');
}

/**
 * Fa partire una raccolta e torna subito.
 *
 * Le raccolte durano da venti secondi a cinque minuti: troppo per una
 * pagina web, che verrebbe interrotta a metà lasciando il lucchetto
 * chiuso. Quindi si lancia il cron staccato dalla pagina — la pagina
 * risponde subito, il lavoro prosegue per conto suo e racconta tutto nel
 * suo log, che è lo stesso che si legge qui sotto.
 *
 * Se l'hosting vieta di lanciare programmi — capita, ed è una scelta
 * legittima — non si finge che sia andata: si restituisce il comando da
 * incollare in un processo cron, che è la strada che funziona sempre.
 */
function lanciaRaccolta(string $modo): array
{
    $ammessi = ['--raccogli', '--raccogli-altre', '--diagnosi'];
    if (!in_array($modo, $ammessi, true)) {
        return ['ko', 'Raccolta sconosciuta.'];
    }

    $comando = escapeshellarg(phpCli()) . ' -q '
             . escapeshellarg(dirname(__DIR__) . '/cron/copertine.php') . ' ' . $modo;

    $vietate = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (!function_exists('exec') || in_array('exec', $vietate, true)) {
        return ['ko', 'Questo hosting non permette di lanciare programmi dalle pagine. '
                    . 'Il comando da mettere in un processo cron è: ' . $comando];
    }

    // Staccata davvero: senza il reindirizzamento dell'uscita la pagina
    // resterebbe ad aspettare la fine del programma, che è esattamente
    // ciò che stiamo evitando.
    @exec($comando . ' > /dev/null 2>&1 &');

    $quanto = $modo === '--raccogli-altre' ? 'cinque minuti circa'
            : ($modo === '--diagnosi' ? 'un minuto' : 'una ventina di secondi');
    return ['ok', 'Raccolta avviata: ci mette ' . $quanto
                . '. Il resoconto compare qui sotto, aggiorna la pagina per vederlo.'];
}

/** Le ultime righe del log delle copertine, per leggerlo dal pannello. */
function codaLog(int $righe = 40): string
{
    $f = dirname(__DIR__) . '/logs/copertine.log';
    if (!is_file($f)) { return ''; }
    $tutte = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    return implode("\n", array_slice($tutte, -$righe));
}
