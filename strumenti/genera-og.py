#!/usr/bin/env python3
"""
genera-og.py — immagine di anteprima per la condivisione (1200x630).

È quella che compare quando qualcuno incolla un link del sito su
WhatsApp, Telegram o Facebook. 1200x630 è la misura che tutte le
piattaforme si aspettano: più stretta la ritagliano, più larga la
rimpiccioliscono.

La composizione è una pagina HTML resa da Chrome in modalità headless,
non un disegno costruito a mano: così usa gli stessi font, gli stessi
colori e lo stesso pattern del sito, e se il sito cambia basta
rigenerarla.

    python3 strumenti/genera-og.py
"""
import re
import subprocess
from pathlib import Path

RADICE = Path(__file__).resolve().parent.parent
ASSETS = RADICE / 'web' / 'assets'
CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
HTML = RADICE / 'strumenti' / '.og.html'
USCITA = ASSETS / 'og.png'

L, A = 1200, 630
# Si rende al doppio e si spedisce così: 2400x1260. Le piattaforme
# accettano immagini più grandi del minimo e le mostrano più nitide sugli
# schermi ad alta densità, e 1200x630 resta la proporzione richiesta.
SCALA = 2


def costruisci_html() -> None:
    """La grafica fornita contiene già il marchio DEFTONES.IT: metterle
    accanto anche il logo del sito lo farebbe comparire due volte. Sta al
    centro da sola, con il payoff sotto e il pattern appena accennato."""
    marchio = RADICE / 'materiali' / 'logo-basic-tshirt.png'
    sfondo = ASSETS / 'sfondo-4.svg'

    HTML.write_text(f"""<!doctype html><meta charset="utf-8">
<style>
  @font-face {{
    font-family: "Inter";
    src: url("file://{ASSETS}/fonts/inter-latin-normal.woff2") format("woff2");
    font-weight: 100 900;
  }}
  * {{ margin: 0; padding: 0; box-sizing: border-box; }}
  html, body {{ width: {L}px; height: {A}px; overflow: hidden; }}
  body {{
    background: #000;
    font-family: "Inter", -apple-system, Helvetica, sans-serif;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    position: relative;
  }}
  /* Il pattern del sito, tenuto molto basso: la grafica al centro è
     fatta delle stesse lettere, e alzarlo creerebbe solo confusione. */
  .fondo {{
    position: absolute; inset: 0;
    background: url("file://{sfondo}") center / 62% auto repeat;
    opacity: .12;
  }}
  .velo {{
    position: absolute; inset: 0;
    background: radial-gradient(58% 78% at 50% 46%,
                rgba(0,0,0,.86) 0%, rgba(0,0,0,.35) 100%);
  }}
  .marchio {{ position: relative; }}
  .marchio img {{ height: 430px; width: auto; display: block; }}
  .payoff {{
    position: relative;
    margin-top: 30px;
    font-size: 21px;
    letter-spacing: 5px;
    text-transform: uppercase;
    color: #8f8d8a;
    white-space: nowrap;
  }}
  .payoff b {{ color: #3ea84b; font-weight: 400; }}
</style>
<div class="fondo"></div><div class="velo"></div>
<div class="marchio"><img src="file://{marchio}" alt=""></div>
<div class="payoff">The Italian Deftones fan site <b>since 2002</b></div>
""", encoding='utf-8')


def rendi() -> None:
    # Chrome scrive rumore su stderr e restituisce un codice diverso da
    # zero anche quando l'immagine esce bene: si controlla il file, non
    # il codice di uscita.
    subprocess.run([
        CHROME, '--headless', '--disable-gpu', '--hide-scrollbars',
        f'--force-device-scale-factor={SCALA}',
        f'--screenshot={USCITA}', f'--window-size={L},{A}',
        f'file://{HTML}',
    ], capture_output=True)

    HTML.unlink(missing_ok=True)
    if not USCITA.exists():
        raise SystemExit('Chrome non ha prodotto l\'immagine')


if __name__ == '__main__':
    costruisci_html()
    rendi()
    print(f'  og.png  {L * SCALA}x{A * SCALA}  ·  {USCITA.stat().st_size / 1024:.0f} KB')
