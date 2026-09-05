<?php
/**
 * icone.php — le iconcine del pannello.
 *
 * Disegnate a contorno con lo stesso tratto delle altre del sito — la
 * lente, la condivisione, il sigillo — così il pannello non sembra un
 * pezzo staccato.
 *
 * Stanno in PHP e non in un file di caratteri: sono una quindicina, si
 * colorano da sole con currentColor, e un carattere iconografico
 * sarebbe una richiesta in più per ogni pagina e un motivo in più per
 * cui qualcosa può non arrivare.
 */
declare(strict_types=1);

const ICONE = [
    // azioni sulle bozze
    'pubblica'  => '<path d="M2.5 8.5 6 12l7.5-8"/>',
    'scarta'    => '<path d="M4 4l8 8M12 4l-8 8"/>',
    'ritira'    => '<path d="M6 3.5 2.5 7 6 10.5"/><path d="M2.5 7h6.8a3.7 3.7 0 0 1 0 7.4H6"/>',
    'modifica'  => '<path d="M11.4 2.3 13.7 4.6 5.3 13H3v-2.3z"/>',
    'anteprima' => '<path d="M1 8s2.6-4.5 7-4.5S15 8 15 8s-2.6 4.5-7 4.5S1 8 1 8Z"/>'
                 . '<circle cx="8" cy="8" r="2"/>',
    'nuovo'     => '<path d="M8 3v10M3 8h10"/>',
    'salva'     => '<path d="M2.5 3.5h8L13.5 6v6.5h-11z"/><path d="M5 3.5v3h5v-3"/>',

    // copertine e immagini
    'immagine'  => '<rect x="2" y="3" width="12" height="10" rx="1.5"/>'
                 . '<circle cx="5.8" cy="6.5" r="1.1"/><path d="M2.4 11 6 8l2.4 2 2.6-2.6 2.6 2.6"/>',
    'cambia'    => '<path d="M2.5 6.5A5.5 5.5 0 0 1 13 5.2"/><path d="M13.3 2.4v3h-3"/>'
                 . '<path d="M13.5 9.5A5.5 5.5 0 0 1 3 10.8"/><path d="M2.7 13.6v-3h3"/>',

    // stato e navigazione
    'apertura'  => '<path d="M8 1.8 9.9 5.7l4.3.6-3.1 3 .7 4.3L8 11.6l-3.8 2 .7-4.3-3.1-3 4.3-.6z"/>',
    'cache'     => '<path d="M2.5 4.5h11"/><path d="M6.5 4.5V3h3v1.5"/>'
                 . '<path d="M4 4.5l.7 8.2h6.6L12 4.5"/>',
    'esci'      => '<path d="M6 2.5H3.2v11H6"/><path d="M9.5 5 12.5 8l-3 3"/><path d="M12.5 8H6"/>',
    'fuori'     => '<path d="M9.5 2.5h4v4"/><path d="M13.5 2.5 7 9"/>'
                 . '<path d="M11.5 9.5v3.5a.9.9 0 0 1-.9.9H3.4a.9.9 0 0 1-.9-.9V5.9a.9.9 0 0 1 .9-.9H7"/>',
    'indietro'  => '<path d="M7 3.5 2.5 8 7 12.5"/><path d="M2.5 8h11"/>',

    // il cuore degli articoli
    'cuore'     => '<path d="M8 13.4 2.9 8.5a3 3 0 0 1 4.2-4.3L8 5.1l.9-.9a3 3 0 0 1 4.2 4.3z"/>',

    // raccolta fotografie
    'raccogli'  => '<path d="M8 1.8v8.4"/><path d="M4.8 7 8 10.2 11.2 7"/>'
                 . '<path d="M2.5 11.5v1.7a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-1.7"/>',
    'diagnosi'  => '<path d="M1.8 8h2.9l1.4 3.8 2.2-7.6 1.5 3.8h4.4"/>',
    // Tre colonne: la spesa si guarda per capire com'è cambiata.
    'costi'     => '<path d="M2.5 13.5h11"/><path d="M4.2 13.5v-4"/>'
                 . '<path d="M8 13.5v-8"/><path d="M11.8 13.5v-5.5"/>',
];

/**
 * Un'icona, pronta da mettere dentro a un pulsante.
 * Torna stringa vuota se il nome non esiste: un'icona mancante non deve
 * far sparire il pulsante che la contiene.
 */
function icona(string $nome, int $misura = 14): string
{
    if (!isset(ICONE[$nome])) { return ''; }
    return '<svg class="icona" viewBox="0 0 16 16" width="' . $misura . '" height="' . $misura . '"'
         . ' fill="none" stroke="currentColor" stroke-width="1.4"'
         . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . ICONE[$nome] . '</svg>';
}
