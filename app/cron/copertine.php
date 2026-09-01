<?php
/**
 * copertine.php — assegna un'immagine di copertina agli articoli.
 *
 * Due mestieri in un file solo, perché sono le due metà della stessa cosa:
 *
 *   --raccogli-flickr  fa lo stesso su Flickr, dove le foto con
 *                licenza libera sono molte di più: su Commons arriva
 *                solo quello che qualcuno si prende la briga di
 *                trasferire. Serve una chiave gratuita in config.php.
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
require __DIR__ . '/../lib/flickr.php';

$avvio = microtime(true);
$opz = $argv ?? [];
$soloProva = in_array('--prova', $opz, true);
$raccogli  = in_array('--raccogli', $opz, true);
$flickr    = in_array('--raccogli-flickr', $opz, true);
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
$cartella = cartellaCopertine();
if (!$soloProva && !is_dir($cartella) && !@mkdir($cartella, 0755, true) && !is_dir($cartella)) {
    allarme('Non riesco a creare ' . $cartella . ' — copertine saltate.', 'copertine');
    exit(1);
}

// =====================================================================
//  Raccolta: Flickr -> df_immagini
// =====================================================================
if ($flickr) {
    if (!cfg('flickr_key')) {
        allarme('Manca flickr_key in config.php: la chiave è gratuita, '
              . 'da flickr.com/services/apps/create', 'copertine');
        exit(1);
    }

    // Si cerca per tag e non a testo libero: "deftones" scritto in una
    // didascalia compare in mille foto che non c'entrano, mentre chi
    // mette quel tag sta dicendo che il soggetto è quello. E si chiedono
    // due tag insieme per le persone: "chino" da solo è un cognome
    // diffuso, "deftones + chino" no.
    $ricerche = [
        'deftones'                  => 'band',
        'deftones,chinomoreno'      => 'chino',
        'deftones,chino'            => 'chino',
        'deftones,stephencarpenter' => 'stephen',
        'deftones,sergiovega'       => 'sergio',
        'deftones,abecunningham'    => 'abe',
        'deftones,frankdelgado'     => 'frank',
        'deftones,chicheng'         => 'chi',
    ];

    $inserisci = $pdo->prepare('INSERT INTO ' . t('immagini') . '
          (riferimento, provenienza, url_file, url_pagina, autore, licenza, licenza_url,
           larghezza, altezza, data_foto, soggetto)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
           url_file = VALUES(url_file), autore = VALUES(autore),
           licenza  = VALUES(licenza),  licenza_url = VALUES(licenza_url),
           data_foto = VALUES(data_foto)');

    $viste = $nuove = $scartate = 0;
    foreach ($ricerche as $tag => $soggetto) {
        $foto = flickrCerca($tag);
        $buone = 0;
        foreach ($foto as $i) {
            $viste++;
            if (!immagineAdatta($i)) { $scartate++; continue; }
            $buone++;
            if ($soloProva) { $nuove++; continue; }
            try {
                $inserisci->execute([
                    $i['riferimento'], 'flickr',
                    $i['url_file'], $i['url_pagina'],
                    $i['autore'] ?: null, $i['licenza'], $i['licenza_url'],
                    $i['larghezza'], $i['altezza'], $i['data'], $soggetto,
                ]);
                if ($inserisci->rowCount() === 1) { $nuove++; }
            } catch (Throwable $e) {
                logline('Non salvata: ' . $i['riferimento'] . ' — ' . $e->getMessage(), 'copertine');
            }
        }
        logline(sprintf('  %-30s %3d trovate  %3d utilizzabili', $tag, count($foto), $buone), 'copertine');
    }

    logline(sprintf('Flickr: %d viste, %d nuove, %d non utilizzabili', $viste, $nuove, $scartate), 'copertine');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
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
          (riferimento, provenienza, url_file, url_pagina, autore, licenza, licenza_url,
           larghezza, altezza, data_foto, soggetto)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
           url_file = VALUES(url_file), autore = VALUES(autore),
           licenza  = VALUES(licenza),  licenza_url = VALUES(licenza_url),
           data_foto = VALUES(data_foto)');

    foreach ($daVisitare as $cat => $soggetto) {
        $file = commonsFileDi($cat);
        if (!$file) { continue; }
        foreach (commonsMetadati($file) as $i) {
            $trovate++;
            if (!immagineAdatta($i)) { $scartate++; continue; }
            if ($soloProva) { $nuove++; continue; }
            try {
                $inserisci->execute([
                    substr($i['commons'], strlen('File:')), 'commons',
                    $i['url_file'], $i['url_pagina'],
                    $i['autore'] ?: null, $i['licenza'] ?: null,
                    $i['licenza_url'] ?: null,
                    $i['larghezza'], $i['altezza'], $i['data'], $soggetto,
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

$dischi = dischiPerTitolo($pdo);
$quante = (int)$pdo->query('SELECT COUNT(*) FROM ' . t('immagini') . '
                             WHERE scartata = 0')->fetchColumn();
logline(sprintf('%d foto nel catalogo', $quante), 'copertine');

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

// La scelta vera sta in assegnaCopertina(), nella libreria: la usa anche
// il pulsante "pubblica con copertina" del pannello, e una logica del
// genere in due copie diverge sempre.
$conti = ['disco' => 0, 'commons' => 0, 'senza' => 0];

foreach ($articoli as $a) {
    $r = assegnaCopertina($pdo, $a, $dischi, $soloProva);
    $conti[$r['origine'] ?? 'senza']++;
    logline(sprintf('  %-9s %-52s %s', $r['origine'] ?? 'senza',
        mb_substr((string)$a['titolo_it'], 0, 52),
        mb_substr($r['nota'], 0, 40)), 'copertine');
}

// La cache va svuotata o le pagine continuerebbero a uscire senza foto.
if (!$soloProva && array_sum($conti) > 0) { cacheSvuota(); }

logline(sprintf('Fatto in %.1fs — %d da disco, %d da Commons, %d senza',
    microtime(true) - $avvio, $conti['disco'], $conti['commons'], $conti['senza']), 'copertine');
logline(str_repeat('-', 60), 'copertine');

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
