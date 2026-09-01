<?php
/**
 * flickr.php — le fotografie con licenza libera su Flickr.
 *
 * Perché anche Flickr, avendo già Commons: un quinto delle foto che
 * abbiamo viene da Flickr, ma solo perché qualcuno si è preso la briga
 * di trasferirlo. Su Commons arriva ciò che qualcuno trasferisce; su
 * Flickr c'è tutto il resto.
 *
 * Il modello legale è lo stesso — Creative Commons con attribuzione —
 * quindi le foto finiscono nello stesso catalogo, con lo stesso filtro
 * e gli stessi crediti. Cambia solo da dove arrivano.
 *
 * Serve una chiave, gratuita, da flickr.com/services/apps/create, da
 * mettere in config.php come 'flickr_key'.
 */
declare(strict_types=1);

/**
 * Le licenze che accettiamo, con il nome e l'atto da linkare.
 *
 * Mancano di proposito tutte le NC e le ND. Non perché il sito sia
 * commerciale — non lo è — ma perché "non commerciale" è una nozione
 * che nessuno sa definire con precisione, e un sito con un giorno un
 * banner o un link affiliato ci finirebbe dentro senza accorgersene.
 * Meglio tenere lo stesso metro di Commons: se è libera, è libera.
 */
const FLICKR_LICENZE = [
    '4'  => ['CC BY 2.0',       'https://creativecommons.org/licenses/by/2.0/'],
    '5'  => ['CC BY-SA 2.0',    'https://creativecommons.org/licenses/by-sa/2.0/'],
    '7'  => ['pubblico dominio','https://www.flickr.com/commons/usage/'],
    '9'  => ['CC0',             'https://creativecommons.org/publicdomain/zero/1.0/'],
    '10' => ['pubblico dominio','https://creativecommons.org/publicdomain/mark/1.0/'],
];

/** Una chiamata all'API di Flickr. Torna l'array decodificato o null. */
function flickrApi(array $parametri): ?array
{
    $chiave = (string)(cfg('flickr_key') ?? '');
    if ($chiave === '') { return null; }

    $parametri += [
        'api_key' => $chiave,
        'format' => 'json',
        'nojsoncallback' => '1',
    ];
    $r = httpGet('https://api.flickr.com/services/rest/?' . http_build_query($parametri));
    if ($r['http'] !== 200 || $r['body'] === null) { return null; }

    $d = json_decode($r['body'], true);
    // Flickr risponde 200 anche quando dice di no: l'esito sta dentro.
    if (!is_array($d) || ($d['stat'] ?? '') !== 'ok') { return null; }
    return $d;
}

/**
 * Cerca fotografie e le restituisce nella stessa forma dei metadati di
 * Commons, così chi le salva non deve sapere da dove vengono.
 *
 * Si cerca per TAG e non a testo libero: "deftones" scritto in una
 * didascalia compare in mille foto che non c'entrano, mentre chi mette
 * quel tag sta dicendo che il soggetto è quello.
 */
function flickrCerca(string $tag, int $pagine = 3): array
{
    $fuori = [];
    for ($p = 1; $p <= $pagine; $p++) {
        $d = flickrApi([
            'method'       => 'flickr.photos.search',
            'tags'         => $tag,
            'tag_mode'     => 'all',
            'license'      => implode(',', array_keys(FLICKR_LICENZE)),
            'sort'         => 'relevance',
            'content_type' => '1',          // solo fotografie
            'media'        => 'photos',
            'safe_search'  => '1',
            'per_page'     => '100',
            'page'         => (string)$p,
            'extras'       => 'license,owner_name,date_taken,path_alias,url_l,url_o,o_dims',
        ]);
        if ($d === null) { break; }

        $foto = $d['photos']['photo'] ?? [];
        foreach ($foto as $f) {
            $lic = FLICKR_LICENZE[(string)($f['license'] ?? '')] ?? null;
            // La misura "l" è il lato lungo a 1024: più che sufficiente
            // per una copertina, e un quinto del peso dell'originale.
            $url = (string)($f['url_l'] ?? $f['url_o'] ?? '');
            if (!$lic || $url === '') { continue; }

            $utente = (string)($f['path_alias'] ?: $f['owner'] ?? '');
            $fuori[] = [
                'riferimento' => 'flickr:' . $f['id'],
                'mime'        => 'image/jpeg',
                'url_file'    => $url,
                'url_pagina'  => 'https://www.flickr.com/photos/' . $utente . '/' . $f['id'],
                'larghezza'   => (int)($f['width_l'] ?? $f['width_o'] ?? 0),
                'altezza'     => (int)($f['height_l'] ?? $f['height_o'] ?? 0),
                'l_orig'      => (int)($f['width_o'] ?? $f['width_l'] ?? 0),
                'a_orig'      => (int)($f['height_o'] ?? $f['height_l'] ?? 0),
                'autore'      => (string)($f['ownername'] ?? ''),
                'licenza'     => $lic[0],
                'licenza_url' => $lic[1],
                'vincoli'     => '',
                'data'        => mb_substr((string)($f['datetaken'] ?? ''), 0, 10) ?: null,
                'titolo'      => (string)($f['title'] ?? ''),
            ];
        }
        // Meno di cento risultati vuol dire che la pagina era l'ultima.
        if (count($foto) < 100) { break; }
        usleep(400000);
    }
    return $fuori;
}
