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

const OPENVERSE_API   = 'https://api.openverse.org/v1/images/';
const OPENVERSE_TOKEN = 'https://api.openverse.org/v1/auth_tokens/token/';

/**
 * Il gettone di accesso, se ci sono le credenziali.
 *
 * Senza credenziali Openverse concede venti richieste al minuto e
 * duecento al giorno, contate PER INDIRIZZO IP. Su un hosting condiviso
 * quell'indirizzo è lo stesso di centinaia di altri siti: la quota può
 * risultare esaurita da gente che non sa nemmeno di averla usata, e la
 * risposta non è un errore chiaro — la richiesta resta appesa finché non
 * scade. Con le credenziali la quota è legata a quelle, e sale a
 * diecimila richieste al giorno.
 *
 * Il gettone dura dodici ore e si conserva in un file: chiederne uno
 * nuovo a ogni pagina sarebbe una richiesta sprecata su due.
 */
function openverseGettone(): ?string
{
    $id = (string)(cfg('openverse_id') ?? '');
    $segreto = (string)(cfg('openverse_secret') ?? '');
    if ($id === '' || $segreto === '') { return null; }

    $file = dirname(__DIR__) . '/cache/openverse-gettone.json';
    $c = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
    if (is_array($c) && ($c['scade'] ?? 0) > time() + 60) { return (string)$c['gettone']; }

    $ch = curl_init(OPENVERSE_TOKEN);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => cfg('user_agent'),
        CURLOPT_POSTFIELDS     => http_build_query([
            'client_id'     => $id,
            'client_secret' => $segreto,
            'grant_type'    => 'client_credentials',
        ]),
    ]);
    $corpo = curl_exec($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $d = is_string($corpo) ? json_decode($corpo, true) : null;
    if ($http !== 200 || !is_array($d) || empty($d['access_token'])) {
        logline('Openverse: credenziali rifiutate (http ' . $http . ')', 'copertine');
        return null;
    }

    @file_put_contents($file, json_encode([
        'gettone' => $d['access_token'],
        'scade'   => time() + (int)($d['expires_in'] ?? 43200),
    ]));
    return (string)$d['access_token'];
}

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

        $indirizzo = OPENVERSE_API . '?' . http_build_query([
            'q'         => $domanda,
            'license'   => openverseLicenze(),
            'page_size' => '20',     // il massimo concesso senza chiave
            'page'      => (string)$p,
        ]);

        // Riprova, e dice perché quando rinuncia.
        //
        // La prima versione usciva in silenzio a ogni intoppo, e il log
        // diceva "0 trovate" — che è la stessa cosa che direbbe se
        // l'archivio fosse vuoto. È l'errore già fatto con MusicBrainz:
        // un guasto di rete travestito da dato mancante non sembra un
        // guasto, e si finisce a cercare la causa dalla parte sbagliata.
        //
        // Il tempo d'attesa è quaranta secondi e non i quindici
        // consueti: la ricerca di Openverse lavora su un indice enorme e
        // ci mette il suo.
        $d = null;
        foreach ([0, 3, 8] as $tentativo => $pausa) {
            if ($pausa) { sleep($pausa); }
            $g = openverseGettone();
            $r = httpGet($indirizzo, null, null, true, 40,
                         $g ? ['Authorization: Bearer ' . $g] : []);
            if ($r['http'] === 200 && $r['body'] !== null) {
                $x = json_decode($r['body'], true);
                if (is_array($x) && isset($x['results'])) { $d = $x; break; }
                logline('Openverse, risposta illeggibile: '
                    . mb_substr((string)$r['body'], 0, 120), 'copertine');
            } elseif ($tentativo === 2) {
                logline(sprintf('Openverse non risponde (http %d%s) — «%s», pagina %d',
                    $r['http'], $r['error'] ? ', ' . $r['error'] : '', $domanda, $p), 'copertine');
            }
        }
        if ($d === null) { break; }

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
