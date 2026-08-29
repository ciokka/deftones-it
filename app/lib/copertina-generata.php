<?php
/**
 * copertina-generata.php — l'immagine di ripiego.
 *
 * Sta in un file suo, e non insieme al resto delle copertine, perché lo
 * usano due mondi diversi: il cron quando assegna, e le pagine quando
 * disegnano. Le pagine non devono tirarsi dietro il codice che parla con
 * Wikimedia per mostrare tre lettere.
 *
 * L'immagine non viene salvata da nessuna parte: dipende solo dallo slug,
 * quindi si ricostruisce identica ogni volta e finisce nell'HTML della
 * pagina, dentro la cache che c'è già. Nessun file da scrivere, nessuno
 * da ripulire, e il giorno che il disegno cambia cambiano tutte insieme.
 */
declare(strict_types=1);

require_once __DIR__ . '/lettere.php';

/**
 * L'immagine di ripiego: tre lettere del pattern del sito, grandi,
 * appena visibili, disposte in modo diverso per ogni articolo ma sempre
 * uguale per lo stesso articolo. Il seme è lo slug, quindi la copertina
 * di un pezzo non cambia da una visita all'altra.
 */
function copertinaGenerata(string $slug): string
{
    [$lw, $lh] = LETTERE_RIQUADRO;
    $cx = $lw / 2; $cy = $lh / 2;
    $L = 1200; $A = 675;

    $seme = crc32($slug) & 0x7FFFFFFF;
    $caso = function (int $n) use (&$seme): int {
        $seme = ($seme * 1103515245 + 12345) & 0x7FFFFFFF;
        return intdiv($seme, 65536) % max(1, $n);
    };

    // Tre lettere diverse — ripeterne una sulla stessa copertina si nota.
    $lettere = array_keys(LETTERE);
    $scelte = [];
    while (count($scelte) < 3) {
        $l = $lettere[$caso(count($lettere))];
        if (!in_array($l, $scelte, true)) { $scelte[] = $l; }
    }

    // Una lettera per terzo dello spazio, con l'ordine dei terzi mescolato:
    // la posizione resta varia da un articolo all'altro, ma non capita più
    // la copertina con metà immagine vuota.
    $terzi = [0, 1, 2];
    for ($i = 2; $i > 0; $i--) {
        $j = $caso($i + 1);
        [$terzi[$i], $terzi[$j]] = [$terzi[$j], $terzi[$i]];
    }

    $piani = [[4.3, .075], [3.2, .11], [2.2, .19]];
    $corpi = '';
    foreach ($piani as $k => [$base, $opacita]) {
        $scala = $base + $caso(50) / 100;
        $x = intdiv($L, 6) + $terzi[$k] * intdiv($L, 3) + $caso(220) - 110;
        $y = intdiv($A, 2) + $caso(300) - 150;
        // Il tratto è costante sullo schermo, quindi va diviso per la
        // scala: senza questo le lettere grandi avrebbero un bordo spesso.
        $tratto = round(1.7 / $scala, 3);
        $corpi .= sprintf(
            '<g transform="translate(%d %d) rotate(-14) scale(%.2f) translate(%.2f %.2f)"'
            . ' stroke-width="%s" opacity="%s"><path d="%s"/></g>',
            $x, $y, $scala, -$cx, -$cy, $tratto, $opacita, LETTERE[$scelte[$k]]
        );
    }

    // Le stesse due luci del fondo del sito, così la copertina generata
    // sembra un ritaglio della pagina invece di un'immagine estranea.
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $L . ' ' . $A . '"'
        . ' width="' . $L . '" height="' . $A . '" role="img" aria-label="deftones.it">'
        . '<defs>'
        . '<radialGradient id="v" cx="80%" cy="-8%" r="58%">'
        . '<stop offset="0" stop-color="#3ea84b" stop-opacity=".22"/>'
        . '<stop offset="1" stop-color="#3ea84b" stop-opacity="0"/>'
        . '</radialGradient>'
        . '<radialGradient id="b" cx="14%" cy="-6%" r="52%">'
        . '<stop offset="0" stop-color="#fff" stop-opacity=".08"/>'
        . '<stop offset="1" stop-color="#fff" stop-opacity="0"/>'
        . '</radialGradient>'
        . '</defs>'
        . '<rect width="' . $L . '" height="' . $A . '" fill="#000"/>'
        . '<g fill="none" stroke="#fff">' . $corpi . '</g>'
        . '<rect width="' . $L . '" height="' . $A . '" fill="url(#v)"/>'
        . '<rect width="' . $L . '" height="' . $A . '" fill="url(#b)"/>'
        . '</svg>';
}
