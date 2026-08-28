#!/usr/bin/env python3
"""
genera-sfondo.py — costruisce gli strati dello sfondo animato.

Legge materiali/lettere.svg (sette lettere, un tracciato ciascuna) e
produce web/assets/sfondo-N.svg: un file per piano di profondità.

Ogni piano ha lettere di una certa dimensione, in una certa quantità, e
il sito lo fa scorrere a una certa velocità. Dimensione, densità,
velocità e opacità crescono insieme: è ciò che rende la scena profonda
invece di farla sembrare tre fogli sovrapposti.

Il risultato è deterministico — stesso seme, stessa disposizione — così
rigenerarlo non stravolge il sito per caso. Cambia SEME per una scena
diversa.

    python3 strumenti/genera-sfondo.py
"""
import math
import random
import re
from pathlib import Path

RADICE = Path(__file__).resolve().parent.parent
SORGENTE = RADICE / 'materiali' / 'lettere.svg'
DESTINAZIONE = RADICE / 'web' / 'assets'

SEME = 20260828
PIASTRELLA = 1000.0        # lo spazio si ripete ogni 1000 unità
TRATTO = 1.1               # spessore del contorno, in unità di piastrella

# Tutti gli strati vengono ingranditi allo stesso modo dal CSS: è ciò che
# rende il contorno identico su ogni piano. Se le scale fossero diverse,
# quel secondo ingrandimento moltiplicherebbe anche lo spessore, e il
# piano vicino risulterebbe disegnato con un pennarello più grosso.
PAROLA = 'DEFTONES'        # da cui si pescano le lettere, con le loro frequenze

# altezza in unità di piastrella · quante · rotazione massima in gradi
#
# Quattro piani, con un intervallo di dimensioni stretto: da 70 a 195,
# cioè meno di tre volte. Con sei piani e quindici volte di differenza
# il piano vicino non sembrava vicino, sembrava un altro disegno.
PIANI = [
    {'altezza':  70, 'quante': 36, 'rotazione': 24},
    {'altezza': 100, 'quante': 26, 'rotazione': 21},
    {'altezza': 140, 'quante': 18, 'rotazione': 18},
    {'altezza': 195, 'quante': 11, 'rotazione': 15},
]


def leggi_lettere() -> tuple[dict[str, str], float]:
    """Restituisce {lettera: dati del tracciato} e l'altezza del viewBox."""
    testo = SORGENTE.read_text(encoding='utf-8')
    vb = re.search(r'viewBox="([\d.\-\s]+)"', testo).group(1).split()
    altezza_naturale = float(vb[3])
    lettere = {}
    for m in re.finditer(r'<path[^>]*\sid="([A-Z])"[^>]*\sd="([^"]+)"', testo):
        lettere[m.group(1)] = m.group(2)
    return lettere, altezza_naturale


def genera() -> None:
    lettere, altezza_naturale = leggi_lettere()
    if not lettere:
        raise SystemExit('nessuna lettera trovata in lettere.svg')
    # solo le lettere che esistono davvero nel file
    alfabeto = [c for c in PAROLA if c in lettere]

    for n, piano in enumerate(PIANI, start=1):
        rnd = random.Random(SEME + n * 977)
        k = piano['altezza'] / altezza_naturale        # fattore di scala
        pezzi = []

        for _ in range(piano['quante']):
            c = rnd.choice(alfabeto)
            x = rnd.uniform(0, PIASTRELLA)
            y = rnd.uniform(0, PIASTRELLA)
            r = rnd.uniform(-piano['rotazione'], piano['rotazione'])

            # Raggio che circoscrive la lettera ruotata: serve a sapere se
            # sborda dalla piastrella e va ripetuta sul lato opposto.
            raggio = piano['altezza'] * 0.75

            # Lo spessore del contorno viene diviso per la scala, così dopo
            # la trasformazione resta uguale su tutti i piani: altrimenti le
            # lettere piccole sparirebbero e le grandi diventerebbero pesanti.
            stile = (f'fill:none;stroke:#fff;stroke-miterlimit:10;'
                     f'stroke-width:{TRATTO / k:.3f}px')

            # La stessa lettera viene ripetuta oltre i bordi che tocca:
            # è ciò che rende la piastrella continua quando si ripete.
            dx = [0.0]
            dy = [0.0]
            if x - raggio < 0:            dx.append(PIASTRELLA)
            if x + raggio > PIASTRELLA:   dx.append(-PIASTRELLA)
            if y - raggio < 0:            dy.append(PIASTRELLA)
            if y + raggio > PIASTRELLA:   dy.append(-PIASTRELLA)

            for ox in dx:
                for oy in dy:
                    pezzi.append(
                        f'<path d="{lettere[c]}" style="{stile}" '
                        f'transform="translate({x + ox:.2f} {y + oy:.2f}) '
                        f'rotate({r:.2f}) scale({k:.4f}) '
                        f'translate({-altezza_naturale / 2:.2f} {-altezza_naturale / 2:.2f})"/>'
                    )

        svg = (
            '<?xml version="1.0" encoding="UTF-8"?>'
            f'<svg xmlns="http://www.w3.org/2000/svg" '
            f'viewBox="0 0 {PIASTRELLA:.0f} {PIASTRELLA:.0f}" '
            f'width="{PIASTRELLA:.0f}" height="{PIASTRELLA:.0f}">'
            + ''.join(pezzi) + '</svg>'
        )
        f = DESTINAZIONE / f'sfondo-{n}.svg'
        f.write_text(svg, encoding='utf-8')
        print(f'  sfondo-{n}.svg  {piano["quante"]:>2} lettere alte {piano["altezza"]:>3} '
              f'· {len(pezzi):>3} tracciati con le ripetizioni ai bordi '
              f'· {len(svg) / 1024:5.1f} KB')


if __name__ == '__main__':
    print(f'Genero gli strati dello sfondo (seme {SEME})')
    genera()
