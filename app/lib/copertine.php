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
 *   2. una foto di Wikimedia Commons con licenza libera;
 *   3. un'immagine generata dalle lettere del pattern del sito.
 *
 * Il terzo non è un ripiego triste: è la garanzia che nessun articolo
 * resti spaiato, e che non si sia mai tentati di prendere una foto che
 * non si può prendere solo perché la pagina veniva brutta.
 */
declare(strict_types=1);

require_once __DIR__ . '/copertina-generata.php';

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
    foreach (['fair use', 'non-free', 'nonfree', 'no derivative', 'nc-', 'noncommercial'] as $no) {
        if (str_contains($l, $no)) { return false; }
    }
    return str_starts_with($l, 'cc')
        || str_contains($l, 'public domain')
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
    if ($i['l_orig'] < 800) { return false; }
    // Una copertina orizzontale: i ritratti verticali, ritagliati a 16:9,
    // diventano quasi sempre un mento.
    return $i['l_orig'] >= $i['a_orig'];
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
    foreach ($dischi as $d) {
        $t = mb_strtolower((string)$d['titolo']);
        // "Deftones" e "Covers" come titoli sono troppo generici: il
        // primo compare in ogni articolo, il secondo in mezzi.
        if (mb_strlen($t) < 4 || in_array($t, ['deftones', 'covers'], true)) { continue; }
        if (preg_match('/\b' . preg_quote($t, '/') . '\b/u', $solotitolo)) {
            return ['tipo' => 'disco', 'chiave' => (string)$d['mbid'],
                    'nome' => (string)$d['titolo']];
        }
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
