<?php
/**
 * copertine.php — assegna un'immagine di copertina agli articoli.
 *
 * Due mestieri in un file solo, perché sono le due metà della stessa cosa:
 *
 *   --raccogli-altre   cerca su Openverse, che indicizza le foto libere
 *                di Flickr e di altri archivi: su Commons arriva solo
 *                quello che qualcuno si prende la briga di trasferire.
 *                Non serve nessuna chiave.
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
 *   --diagnosi   tre domande di prova a Openverse: dice a che punto si
 *                rompe e quanta quota resta. Non scrive niente.
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
require __DIR__ . '/../lib/openverse.php';

$avvio = microtime(true);
$opz = $argv ?? [];
$soloProva = in_array('--prova', $opz, true);
$raccogli  = in_array('--raccogli', $opz, true);
$altre     = in_array('--raccogli-altre', $opz, true);
$diagnosi  = in_array('--diagnosi', $opz, true);
$rifai     = in_array('--rifai', $opz, true);
$limite    = 40;
foreach ($opz as $o) {
    if (preg_match('/^--limite=(\d+)$/', $o, $m)) { $limite = max(1, (int)$m[1]); }
}

$lock = prendiLock('copertine');
if ($lock === false) { logline('Un altro giro è in corso — esco.', 'copertine'); exit(0); }

logline(sprintf('PHP %s (%s)%s', PHP_VERSION, PHP_SAPI, $soloProva ? ' — PROVA' : ''), 'copertine');
$pdo = db();

/**
 * I riferimenti già in catalogo, per contare bene durante la prova.
 *
 * In prova non si scrive niente, quindi non c'è un INSERT che possa
 * dire "questa era nuova". Senza questo elenco la prova contava come
 * nuove tutte le utilizzabili, comprese le duecento che erano lì da
 * giorni, e diceva 222 dove la raccolta vera ne inseriva 52.
 */
function giaInCatalogo(PDO $pdo): array
{
    static $elenco = null;
    if ($elenco === null) {
        $elenco = [];
        foreach ($pdo->query('SELECT riferimento FROM ' . t('immagini'))
                 ->fetchAll(PDO::FETCH_COLUMN) as $r) {
            $elenco[(string)$r] = true;
        }
    }
    return $elenco;
}

/** Dove finiscono i file scaricati, sul disco e nell'indirizzo. */
$cartella = cartellaCopertine();
if (!$soloProva && !is_dir($cartella) && !@mkdir($cartella, 0755, true) && !is_dir($cartella)) {
    allarme('Non riesco a creare ' . $cartella . ' — copertine saltate.', 'copertine');
    exit(1);
}

// =====================================================================
//  Diagnosi: capire perché Openverse non risponde
// =====================================================================
if ($diagnosi) {
    openverseDiagnosi();
    logline(sprintf('Fatto in %.1fs.', microtime(true) - $avvio), 'copertine');
    exit(0);
}

// =====================================================================
//  Raccolta: Openverse (Flickr e altri) -> df_immagini
// =====================================================================
if ($altre) {
    // Le domande. Openverse cerca nel titolo, nella descrizione e nei
    // tag: per le persone si mette anche "deftones", o "chino" da solo
    // porta indietro mezzo mondo.
    // Le ultime quattro non cercano una persona ma una situazione. Per
    // un pezzo scritto a mano serve una fotografia che c'entri con
    // quello che racconta, e "deftones" e basta riporta indietro sempre
    // gli stessi ritratti di scena: chi cerca il palco, la folla o il
    // festival deve chiederlo. Restano soggetto 'band' perché valgono
    // per qualunque articolo.
    $ricerche = [
        'deftones'                  => 'band',
        'deftones chino moreno'     => 'chino',
        'deftones stephen carpenter'=> 'stephen',
        'deftones sergio vega'      => 'sergio',
        'deftones abe cunningham'   => 'abe',
        'deftones frank delgado'    => 'frank',
        'deftones chi cheng'        => 'chi',
        'deftones live'             => 'band',
        'deftones concert'          => 'band',
        'deftones festival'         => 'band',
        'deftones tour'             => 'band',
    ];

    $inserisci = $pdo->prepare('INSERT INTO ' . t('immagini') . '
          (riferimento, titolo, provenienza, url_file, url_pagina, autore, licenza, licenza_url,
           larghezza, altezza, data_foto, soggetto)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
           url_file = VALUES(url_file), autore = VALUES(autore), titolo = VALUES(titolo),
           licenza  = VALUES(licenza),  licenza_url = VALUES(licenza_url)');

    logline(cfg('foto_non_commerciali')
        ? 'Licenze accettate: CC BY, BY-SA, BY-NC, BY-NC-SA, CC0, pubblico dominio'
        : 'Licenze accettate: CC BY, BY-SA, CC0, pubblico dominio (niente NC)', 'copertine');

    $viste = $nuove = $scartate = 0;
    foreach ($ricerche as $domanda => $soggetto) {
        // Le ricerche sui singoli membri rendono meno: non ha senso
        // insistere per dodici pagine.
        $foto = openverseCerca($domanda, $soggetto === 'band' ? 8 : 4, $guasto);
        // Se l'archivio non risponde si smette subito. Andare avanti a
        // interrogare un server che ci sta ignorando non porta foto e
        // allunga il periodo in cui ci ignora.
        if ($guasto) {
            logline('Openverse non risponde: interrompo il giro. '
                . 'Riprovare fra ventiquattr\'ore, non prima.', 'copertine');
            break;
        }
        $buone = 0;
        foreach ($foto as $i) {
            $viste++;
            if (!immagineAdatta($i)) { $scartate++; continue; }
            $buone++;
            if ($soloProva) {
                if (!isset(giaInCatalogo($pdo)[$i['riferimento']])) { $nuove++; }
                continue;
            }
            try {
                $inserisci->execute([
                    $i['riferimento'], $i['titolo'] ?: null, $i['provenienza'],
                    $i['url_file'], $i['url_pagina'],
                    $i['autore'] ?: null, $i['licenza'], $i['licenza_url'] ?: null,
                    $i['larghezza'], $i['altezza'], $i['data'], $soggetto,
                ]);
                if ($inserisci->rowCount() === 1) { $nuove++; }
            } catch (Throwable $e) {
                logline('Non salvata: ' . $i['riferimento'] . ' — ' . $e->getMessage(), 'copertine');
            }
        }
        logline(sprintf('  %-30s %3d trovate  %3d utilizzabili',
            mb_substr($domanda, 0, 30), count($foto), $buone), 'copertine');
    }

    logline(sprintf('Openverse: %d viste, %d nuove, %d non utilizzabili',
        $viste, $nuove, $scartate), 'copertine');
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
    logline(cfg('foto_non_commerciali')
        ? 'Licenze accettate: anche le NC'
        : 'Licenze accettate: niente NC', 'copertine');

    $inserisci = $pdo->prepare('INSERT INTO ' . t('immagini') . '
          (riferimento, titolo, provenienza, url_file, url_pagina, autore, licenza, licenza_url,
           larghezza, altezza, data_foto, soggetto)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
           url_file = VALUES(url_file), autore = VALUES(autore), titolo = VALUES(titolo),
           licenza  = VALUES(licenza),  licenza_url = VALUES(licenza_url),
           data_foto = VALUES(data_foto)');

    foreach ($daVisitare as $cat => $soggetto) {
        $file = commonsFileDi($cat);
        if (!$file) { continue; }
        foreach (commonsMetadati($file) as $i) {
            $trovate++;
            if (!immagineAdatta($i)) { $scartate++; continue; }
            $rif = substr($i['commons'], strlen('File:'));
            if ($soloProva) {
                if (!isset(giaInCatalogo($pdo)[$rif])) { $nuove++; }
                continue;
            }
            try {
                $inserisci->execute([
                    $rif, $i['titolo'] ?: null, 'commons',
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
