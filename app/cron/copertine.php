<?php
/**
 * copertine.php — assegna un'immagine di copertina agli articoli.
 *
 * Due mestieri in un file solo, perché sono le due metà della stessa cosa:
 *
 *   --raccogli   interroga Wikimedia Commons e riempie il catalogo
 *                df_immagini. Va fatto ogni tanto, non ogni giorno: le
 *                foto libere dei Deftones non nascono al ritmo delle
 *                notizie.
 *
 *   (senza)      assegna una copertina agli articoli che non ce l'hanno,
 *                pescando dal catalogo. Nessuna chiamata a Commons,
 *                nessuna chiamata all'IA: costo zero, e rilanciabile
 *                quante volte vuoi.
 *
 * Opzioni:
 *   --prova      dice cosa farebbe senza scrivere niente
 *   --limite=N   quanti articoli trattare in questo giro (predefinito 40)
 *   --rifai      rimette in gioco anche gli articoli già assegnati
 *                automaticamente. Non tocca le copertine messe a mano.
 *
 * Uso:  /opt/cpanel/ea-php83/root/usr/bin/php -q \
 *         /home/bpdefton/deftones/app/cron/copertine.php
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/web.php';       // per cacheSvuota()
require __DIR__ . '/../lib/copertine.php';

$avvio = microtime(true);
$opz = $argv ?? [];
$soloProva = in_array('--prova', $opz, true);
$raccogli  = in_array('--raccogli', $opz, true);
$rifai     = in_array('--rifai', $opz, true);
$limite    = 40;
foreach ($opz as $o) {
    if (preg_match('/^--limite=(\d+)$/', $o, $m)) { $limite = max(1, (int)$m[1]); }
}

$lock = prendiLock('copertine');
if ($lock === false) { logline('Un altro giro è in corso — esco.', 'copertine'); exit(0); }

logline(sprintf('PHP %s (%s)%s', PHP_VERSION, PHP_SAPI, $soloProva ? ' — PROVA' : ''), 'copertine');
$pdo = db();

/** Dove finiscono i file scaricati, sul disco e nell'indirizzo. */
$cartella = rtrim((string)(cfg('media_dir') ?: '/home/bpdefton/public_html/media'), '/')
          . '/copertine';
if (!$soloProva && !is_dir($cartella) && !@mkdir($cartella, 0755, true) && !is_dir($cartella)) {
    allarme('Non riesco a creare ' . $cartella . ' — copertine saltate.', 'copertine');
    exit(1);
}

// =====================================================================
//  Raccolta: Commons -> df_immagini
// =====================================================================
if ($raccogli) {
    $trovate = $nuove = $scartate = 0;

    // Le sottocategorie di Category:Deftones sono i singoli concerti:
    // Hellfest 2010, Knotfest México 2016, Rock im Park 2022… È lì che
    // stanno le foto buone, non nella categoria madre.
    $daVisitare = [];
    foreach (COMMONS_CATEGORIE as $cat => $soggetto) {
        $daVisitare[$cat] = $soggetto;
        if ($soggetto === 'band') {
            foreach (commonsSottocategorie($cat) as $sub) { $daVisitare[$sub] = 'band'; }
        }
    }
    logline(sprintf('%d categorie da visitare', count($daVisitare)), 'copertine');

    $inserisci = $pdo->prepare('INSERT INTO ' . t('immagini') . '
          (commons, url_file, url_pagina, autore, licenza, licenza_url,
           larghezza, altezza, soggetto)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
           url_file = VALUES(url_file), autore = VALUES(autore),
           licenza  = VALUES(licenza),  licenza_url = VALUES(licenza_url)');

    foreach ($daVisitare as $cat => $soggetto) {
        $file = commonsFileDi($cat);
        if (!$file) { continue; }
        foreach (commonsMetadati($file) as $i) {
            $trovate++;
            if (!immagineAdatta($i)) { $scartate++; continue; }
            if ($soloProva) { $nuove++; continue; }
            try {
                $inserisci->execute([
                    substr($i['commons'], strlen('File:')),
                    $i['url_file'], $i['url_pagina'],
                    $i['autore'] ?: null, $i['licenza'] ?: null,
                    $i['licenza_url'] ?: null,
                    $i['larghezza'], $i['altezza'], $soggetto,
                ]);
                if ($inserisci->rowCount() === 1) { $nuove++; }
            } catch (Throwable $e) {
                logline('Non salvata: ' . $i['commons'] . ' — ' . $e->getMessage(), 'copertine');
            }
        }
        logline(sprintf('  %-46s %3d file', mb_substr($cat, 0, 46), count($file)), 'copertine');
    }

    logline(sprintf('Raccolta finita in %.1fs — %d viste, %d nuove, %d non utilizzabili',
        microtime(true) - $avvio, $trovate, $nuove, $scartate), 'copertine');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}

// =====================================================================
//  Assegnazione: catalogo -> articoli
// =====================================================================

$dischi = $pdo->query('SELECT titolo, mbid FROM ' . t('albums') . '
                        WHERE mbid IS NOT NULL
                        ORDER BY CHAR_LENGTH(titolo) DESC')->fetchAll();

// Il catalogo sta tutto in memoria: sono un paio di centinaia di righe,
// e averlo qui permette di scegliere in PHP invece che con una query per
// articolo. Serve soprattutto a --prova: tenendo il conto degli usi in
// memoria, il giro a vuoto mostra la stessa rotazione che ci sarà per
// davvero, invece di ripetere all'infinito la prima foto dell'elenco.
$catalogo = $pdo->query('SELECT id, url_file, url_pagina, autore, licenza,
                                licenza_url, soggetto, usata
                           FROM ' . t('immagini') . '
                          WHERE scartata = 0
                          ORDER BY id')->fetchAll();
foreach ($catalogo as $k => $c) { $catalogo[$k]['usata'] = (int)$c['usata']; }

// Il catalogo si mescola, con un ordine che resta lo stesso a ogni giro.
// Senza, la scelta scorreva per id e gli id sono raggruppati per
// categoria: venti articoli di fila prendevano venti foto della stessa
// sera, tutte dello stesso fotografo. Mescolando, due articoli vicini
// prendono foto di concerti diversi.
usort($catalogo, fn(array $x, array $y): int
    => strcmp(md5('cop' . $x['id']), md5('cop' . $y['id'])));
logline(sprintf('%d foto nel catalogo', count($catalogo)), 'copertine');

/**
 * Sceglie la foto meno consumata fra quelle buone per un soggetto.
 *
 * Il "usata < 3" non è un dettaglio. Di Stephen Carpenter su Commons ci
 * sono quattro foto libere: senza quel limite ogni articolo che lo nomina
 * si riprendeva quelle quattro in giro, all'infinito, mentre centoventisei
 * foto di gruppo mai usate stavano lì. Dopo tre volte il soggetto giusto
 * smette di avere la precedenza e vince chi è stato usato di meno.
 */
$scegliFoto = function (string $soggetto) use (&$catalogo): ?int {
    $miglior = null; $migliorChiave = null;
    foreach ($catalogo as $k => $c) {
        if ($c['soggetto'] !== $soggetto && $c['soggetto'] !== 'band') { continue; }
        $chiave = [
            ($c['soggetto'] === $soggetto && $c['usata'] < 3) ? 0 : 1,
            $c['usata'],
            $k,                 // l'ordine mescolato, non quello degli id
        ];
        if ($migliorChiave === null || $chiave < $migliorChiave) {
            $migliorChiave = $chiave; $miglior = $k;
        }
    }
    return $miglior;
};

// Quante volte una copertina di disco è già in giro per il sito. Oltre
// una certa soglia la home diventa un muro della stessa busta, quindi
// gli articoli più vecchi su quel disco ripiegano su una foto: quelli
// più recenti la tengono, perché la coda è ordinata dal più nuovo.
const MAX_PER_DISCO = 4;
$usiDisco = [];
foreach ($pdo->query('SELECT immagine_fonte_url AS f, COUNT(*) AS n
                        FROM ' . t('articles') . "
                       WHERE immagine_origine = 'disco'
                         AND immagine_fonte_url IS NOT NULL
                       GROUP BY immagine_fonte_url") as $r) {
    $usiDisco[(string)$r['f']] = (int)$r['n'];
}

// Le copertine dei dischi si scaricano una volta sola: venti articoli su
// 'private music' chiedevano venti volte lo stesso file al Cover Art
// Archive. In --prova serve anche a dire la verità, perché la copertina
// viene davvero cercata: se per un disco non c'è, il log lo dice invece
// di prometterla.
$copertineDisco = [];
$prendiDisco = function (string $mbid) use (&$copertineDisco): ?string {
    if (!array_key_exists($mbid, $copertineDisco)) {
        $copertineDisco[$mbid] = copertinaDisco($mbid);
    }
    return $copertineDisco[$mbid];
};

// Le copertine messe a mano non si toccano mai, nemmeno con --rifai:
// se hai scelto tu quella foto, l'hai scelta.
$dove = $rifai
    ? "(immagine_origine IS NULL OR immagine_origine <> 'manuale')"
    : "immagine_origine IS NULL AND immagine_cercata_il IS NULL";

$articoli = $pdo->query(
    'SELECT id, slug, titolo_it, tag FROM ' . t('articles') . "
      WHERE stato IN ('draft','pubblicato') AND $dove
      ORDER BY pubblicato_il IS NULL, pubblicato_il DESC
      LIMIT $limite"
)->fetchAll();

logline(sprintf('%d articoli da illustrare', count($articoli)), 'copertine');

$salva = $pdo->prepare('UPDATE ' . t('articles') . '
     SET immagine_url = ?, immagine_autore = ?, immagine_licenza = ?,
         immagine_licenza_url = ?, immagine_fonte_url = ?,
         immagine_origine = ?, immagine_cercata_il = NOW()
   WHERE id = ?');
$segnaUsata = $pdo->prepare('UPDATE ' . t('immagini') . '
     SET usata = usata + 1 WHERE id = ?');

$conti = ['disco' => 0, 'commons' => 0, 'generata' => 0];

foreach ($articoli as $a) {
    $tag = json_decode((string)$a['tag'], true) ?: [];
    $s = soggettoArticolo((string)$a['titolo_it'], $tag, $dischi);
    $esito = null;
    $file = $cartella . '/' . $a['slug'] . '.jpg';

    // --- 1. la copertina del disco, se il titolo nomina un disco ------
    if ($s['tipo'] === 'disco' && $s['chiave'] !== '') {
        $rif = 'https://musicbrainz.org/release-group/' . $s['chiave'];
        if (($usiDisco[$rif] ?? 0) < MAX_PER_DISCO) {
            $dati = $prendiDisco($s['chiave']);
            if ($dati !== null && ($soloProva || @file_put_contents($file, $dati) !== false)) {
                $usiDisco[$rif] = ($usiDisco[$rif] ?? 0) + 1;
                $esito = [
                    'url' => '/media/copertine/' . $a['slug'] . '.jpg',
                    'autore' => null,
                    'licenza' => 'copertina di ' . $s['nome'],
                    'licenza_url' => null, 'fonte' => $rif,
                    'origine' => 'disco', 'img' => null,
                    'nota' => $s['nome'],
                ];
            }
        }
    }

    // --- 2. una foto libera dal catalogo ------------------------------
    if ($esito === null) {
        $k = $scegliFoto($s['tipo'] === 'foto' ? $s['chiave'] : 'band');
        if ($k !== null) {
            $img = $catalogo[$k];
            $dati = $soloProva ? 'x' : (httpGet((string)$img['url_file'])['body'] ?? null);
            if ($dati !== null && ($soloProva || strlen($dati) > 5000)) {
                if ($soloProva || @file_put_contents($file, $dati) !== false) {
                    $catalogo[$k]['usata']++;      // conta anche in prova
                    $esito = [
                        'url' => '/media/copertine/' . $a['slug'] . '.jpg',
                        'autore' => $img['autore'],
                        'licenza' => $img['licenza'],
                        'licenza_url' => $img['licenza_url'],
                        'fonte' => $img['url_pagina'],
                        'origine' => 'commons', 'img' => (int)$img['id'],
                        'nota' => (string)$img['autore'],
                    ];
                }
            }
        }
    }

    // --- 3. il ripiego: le lettere del pattern ------------------------
    if ($esito === null) {
        $esito = ['url' => null, 'autore' => null, 'licenza' => null,
                  'licenza_url' => null, 'fonte' => null,
                  'origine' => 'generata', 'img' => null, 'nota' => ''];
    }

    $conti[$esito['origine']]++;
    logline(sprintf('  %-9s %-52s %s', $esito['origine'],
        mb_substr((string)$a['titolo_it'], 0, 52),
        mb_substr((string)$esito['nota'], 0, 40)), 'copertine');

    if ($soloProva) { continue; }
    $salva->execute([$esito['url'], $esito['autore'], $esito['licenza'],
                     $esito['licenza_url'], $esito['fonte'], $esito['origine'], $a['id']]);
    if ($esito['img'] !== null) { $segnaUsata->execute([$esito['img']]); }
}

// La cache va svuotata o le pagine continuerebbero a uscire senza foto.
if (!$soloProva && array_sum($conti) > 0) { cacheSvuota(); }

logline(sprintf('Fatto in %.1fs — %d da disco, %d da Commons, %d generate',
    microtime(true) - $avvio, $conti['disco'], $conti['commons'], $conti['generata']), 'copertine');
logline(str_repeat('-', 60), 'copertine');

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
