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
TRATTO = 1.0               # spessore del contorno, in unità di piastrella

# Tutti gli strati vengono ingranditi allo stesso modo dal CSS: è ciò che
# rende il contorno identico su ogni piano. Se le scale fossero diverse,
# quel secondo ingrandimento moltiplicherebbe anche lo spessore, e il
# piano vicino risulterebbe disegnato con un pennarello più grosso.
PAROLA = 'DEFTONES'        # da cui si pescano le lettere, con le loro frequenze
INCLINAZIONE = -14.0       # gradi, uguale per tutte: le lettere sono un
                           # reticolo, non un mucchio buttato lì

# altezza in unità di piastrella · quante
#
# Quattro piani, con un intervallo di dimensioni stretto: da 70 a 195,
# cioè meno di tre volte. Con sei piani e quindici volte di differenza
# il piano vicino non sembrava vicino, sembrava un altro disegno.
PIANI = [
    {'altezza':  70, 'quante': 24},
    {'altezza': 100, 'quante': 17},
    {'altezza': 140, 'quante': 12},
    {'altezza': 195, 'quante':  8},
]


def ingombro(d: str) -> tuple[float, float, float, float]:
    """Riquadro che contiene il tracciato: (x0, y0, x1, y1).

    Serve per due cose: centrare ogni lettera sul suo centro vero, e
    sapere quanto sporge davvero. Stimare l'ingombro dall'altezza — che
    è quello che facevo prima — va bene per una O ma non per una F, che
    è larga il 30% in meno: le copie ai bordi non venivano disegnate e
    le lettere risultavano tagliate.
    """
    toks = re.findall(r'[MmLlHhVvCcSsQqTtAaZz]|[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?', d)
    x = y = sx = sy = 0.0
    xs: list[float] = []
    ys: list[float] = []
    i = 0
    cmd = None

    def num() -> float:
        nonlocal i
        v = float(toks[i])
        i += 1
        return v

    while i < len(toks):
        t = toks[i]
        if re.match(r'[A-Za-z]', t):
            cmd = t
            i += 1
            if cmd in 'Zz':
                x, y = sx, sy
                continue
        rel = cmd.islower()
        c = cmd.upper()
        try:
            if c == 'M':
                a, b = num(), num()
                x, y = (x + a, y + b) if rel else (a, b)
                sx, sy = x, y
                cmd = 'l' if rel else 'L'
            elif c == 'L':
                a, b = num(), num()
                x, y = (x + a, y + b) if rel else (a, b)
            elif c == 'H':
                a = num()
                x = x + a if rel else a
            elif c == 'V':
                a = num()
                y = y + a if rel else a
            elif c == 'C':
                p = [num() for _ in range(6)]
                pts = ([(x + p[k], y + p[k + 1]) for k in (0, 2, 4)] if rel
                       else [(p[0], p[1]), (p[2], p[3]), (p[4], p[5])])
                xs += [q[0] for q in pts]
                ys += [q[1] for q in pts]
                x, y = pts[-1]
            elif c in 'SQ':
                p = [num() for _ in range(4)]
                pts = ([(x + p[k], y + p[k + 1]) for k in (0, 2)] if rel
                       else [(p[0], p[1]), (p[2], p[3])])
                xs += [q[0] for q in pts]
                ys += [q[1] for q in pts]
                x, y = pts[-1]
            elif c == 'T':
                a, b = num(), num()
                x, y = (x + a, y + b) if rel else (a, b)
            elif c == 'A':
                p = [num() for _ in range(7)]
                x, y = (x + p[5], y + p[6]) if rel else (p[5], p[6])
            else:
                i += 1
                continue
        except (IndexError, ValueError):
            break
        xs.append(x)
        ys.append(y)
    return min(xs), min(ys), max(xs), max(ys)


def leggi_lettere() -> dict[str, dict]:
    """{lettera: {d, cx, cy, larghezza, altezza}} con gli ingombri veri."""
    testo = SORGENTE.read_text(encoding='utf-8')
    lettere = {}
    for m in re.finditer(r'<path[^>]*\sid="([A-Z])"[^>]*\sd="([^"]+)"', testo):
        d = m.group(2)
        x0, y0, x1, y1 = ingombro(d)
        lettere[m.group(1)] = {
            'd': d,
            'cx': (x0 + x1) / 2, 'cy': (y0 + y1) / 2,
            'larghezza': x1 - x0, 'altezza': y1 - y0,
        }
    return lettere


def genera() -> None:
    lettere = leggi_lettere()
    if not lettere:
        raise SystemExit('nessuna lettera trovata in lettere.svg')
    alfabeto = [c for c in PAROLA if c in lettere]

    # L'inclinazione è la stessa per tutte, quindi seno e coseno si
    # calcolano una volta sola.
    rad = math.radians(INCLINAZIONE)
    cos_a, sin_a = abs(math.cos(rad)), abs(math.sin(rad))

    for n, piano in enumerate(PIANI, start=1):
        rnd = random.Random(SEME + n * 977)
        pezzi = []

        for _ in range(piano['quante']):
            c = rnd.choice(alfabeto)
            L = lettere[c]
            # Scala calcolata sull'altezza reale di QUESTA lettera, così
            # tutte risultano alte uguale sul piano.
            k = piano['altezza'] / L['altezza']
            x = rnd.uniform(0, PIASTRELLA)
            y = rnd.uniform(0, PIASTRELLA)

            # Semiassi del riquadro dopo l'inclinazione: è la sporgenza
            # vera, calcolata sull'ingombro di questa lettera e non
            # stimata da un'altezza uguale per tutte.
            hw = L['larghezza'] * k / 2
            hh = L['altezza'] * k / 2
            sx_ = hw * cos_a + hh * sin_a
            sy_ = hw * sin_a + hh * cos_a

            # Lo spessore del contorno viene diviso per la scala, così dopo
            # la trasformazione resta uguale su tutti i piani: altrimenti le
            # lettere piccole sparirebbero e le grandi diventerebbero pesanti.
            stile = (f'fill:none;stroke:#fff;stroke-miterlimit:10;'
                     f'stroke-width:{TRATTO / k:.3f}px')

            # La stessa lettera viene ripetuta oltre i bordi che tocca:
            # è ciò che rende la piastrella continua quando si ripete.
            dx = [0.0]
            dy = [0.0]
            if x - sx_ < 0:            dx.append(PIASTRELLA)
            if x + sx_ > PIASTRELLA:   dx.append(-PIASTRELLA)
            if y - sy_ < 0:            dy.append(PIASTRELLA)
            if y + sy_ > PIASTRELLA:   dy.append(-PIASTRELLA)

            for ox in dx:
                for oy in dy:
                    pezzi.append(
                        f'<path d="{L["d"]}" style="{stile}" '
                        f'transform="translate({x + ox:.2f} {y + oy:.2f}) '
                        f'rotate({INCLINAZIONE:.2f}) scale({k:.4f}) '
                        f'translate({-L["cx"]:.2f} {-L["cy"]:.2f})"/>'
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
