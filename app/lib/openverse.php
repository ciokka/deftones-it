<?php
/**
 * openverse.php — fotografie con licenza libera, da Flickr e non solo.
 *
 * Perché non direttamente da Flickr: dal 2024 Flickr non rilascia più
 * chiavi API agli account gratuiti, solo agli abbonati PRO. Openverse —
 * il motore di ricerca di opere libere della WordPress Foundation —
 * indicizza però proprio quelle fotografie, e risponde senza chiave.
 * Si arriva allo stesso archivio da un'altra porta.
 *
 * I risultati che vengono da Wikimedia si scartano: quelli li
 * raccogliamo già alla fonte, con metadati migliori — compresa la data
 * di scatto, che Openverse non riporta.
 *
 * Senza chiave si possono fare venti richieste al minuto e duecento al
 * giorno, che per una raccolta ogni tanto bastano con margine.
 */
declare(strict_types=1);

const OPENVERSE_API = 'https://api.openverse.org/v1/images/';

/**
 * Le licenze da chiedere a Openverse.
 *
 * Mai le ND: le copertine vengono ritagliate a 16:9, e un ritaglio è
 * un'opera derivata. Le NC dipendono dalla scelta in config.php, la
 * stessa che governa il filtro su Commons: si chiedono all'archivio
 * solo se le accettiamo, o si scaricherebbero per scartarle dopo.
 */
function openverseLicenze(): string
{
    $l = ['by', 'by-sa', 'cc0', 'pdm'];
    if (cfg('foto_non_commerciali')) { $l[] = 'by-nc'; $l[] = 'by-nc-sa'; }
    return implode(',', $l);
}

/**
 * Il nome per esteso di una licenza, come va scritto sotto la foto.
 * Openverse dà il codice e la versione separati: "by-sa" e "2.0".
 */
function nomeLicenza(string $codice, string $versione): string
{
    $c = strtolower(trim($codice));
    if ($c === 'cc0') { return 'CC0'; }
    if ($c === 'pdm') { return 'pubblico dominio'; }
    return trim('CC ' . strtoupper($c) . ' ' . $versione);
}

/**
 * Cerca fotografie e le restituisce nella stessa forma dei metadati di
 * Commons, così chi le salva non deve sapere da dove vengono.
 *
 * Si ferma da sola quando una pagina torna meno piena del massimo:
 * vuol dire che era l'ultima.
 */
function openverseCerca(string $domanda, int $pagine = 12): array
{
    $fuori = [];
    for ($p = 1; $p <= $pagine; $p++) {
        // Venti al minuto è il limite senza chiave: tre secondi e mezzo
        // fra una richiesta e l'altra ci stanno dentro con margine.
        if ($p > 1) { sleep(4); }

        $r = httpGet(OPENVERSE_API . '?' . http_build_query([
            'q'         => $domanda,
            'license'   => openverseLicenze(),
            'page_size' => '20',     // il massimo concesso senza chiave
            'page'      => (string)$p,
        ]));
        if ($r['http'] !== 200 || $r['body'] === null) { break; }
        $d = json_decode($r['body'], true);
        if (!is_array($d) || !isset($d['results'])) { break; }

        foreach ($d['results'] as $x) {
            $fonte = (string)($x['provider'] ?? '');
            // Wikimedia la raccogliamo alla fonte, con la data di scatto
            // che qui non c'è: prenderla anche da qui sarebbe un doppione
            // peggiore dell'originale.
            if ($fonte === 'wikimedia' || $fonte === '') { continue; }

            $url = (string)($x['url'] ?? '');
            $pagina = (string)($x['foreign_landing_url'] ?? '');
            if ($url === '' || $pagina === '') { continue; }

            // Un identificativo che dica qualcosa: per Flickr il numero
            // della fotografia, che è quello vero e che resterebbe
            // valido anche arrivandoci da un'altra strada.
            $rif = preg_match('#flickr\.com/photos/[^/]+/(\d+)#', $pagina, $m)
                ? 'flickr:' . $m[1]
                : 'openverse:' . (string)($x['id'] ?? '');

            $fuori[] = [
                'riferimento' => $rif,
                'provenienza' => $fonte,
                'mime'        => str_ends_with(strtolower($url), '.png') ? 'image/png' : 'image/jpeg',
                'url_file'    => $url,
                'url_pagina'  => $pagina,
                'larghezza'   => (int)($x['width'] ?? 0),
                'altezza'     => (int)($x['height'] ?? 0),
                'l_orig'      => (int)($x['width'] ?? 0),
                'a_orig'      => (int)($x['height'] ?? 0),
                'autore'      => (string)($x['creator'] ?? ''),
                'licenza'     => nomeLicenza((string)($x['license'] ?? ''),
                                             (string)($x['license_version'] ?? '')),
                'licenza_url' => (string)($x['license_url'] ?? ''),
                'vincoli'     => '',
                // Openverse non riporta quando è stata scattata: meglio
                // niente che una data presa da un altro campo.
                'data'        => null,
                'titolo'      => (string)($x['title'] ?? ''),
            ];
        }

        if (count($d['results']) < 20) { break; }
    }
    return $fuori;
}
