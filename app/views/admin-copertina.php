<div class="pannello">
  <p><a class="torna" href="<?= u('admin/modifica/' . (int)$a['id']) ?>">
    <?= icona('indietro') ?> torna all'articolo</a></p>

  <?php if ($messaggio): ?>
    <p class="avviso<?= $messaggio[0] === 'ok' ? 'Ok' : 'Ko' ?>"><?= e($messaggio[1]) ?></p>
  <?php endif ?>

  <h1 class="titoletto">Copertina</h1>
  <p class="occhiello"><?= e($a['titolo_it']) ?></p>

  <div class="copertina-adesso">
    <?php if ($a['immagine_url']): ?>
      <img src="<?= e(urlCopertina($a)) ?>" alt="">
      <div class="copertina-dati">
        <p class="modulo-nota">
          <?= e((string)($a['immagine_autore'] ?: 'autore non registrato')) ?>
          <?php if ($a['immagine_licenza']): ?> · <?= e($a['immagine_licenza']) ?><?php endif ?>
          · scelta <?= $a['immagine_origine'] === 'manuale' ? 'a mano' : 'dal programma' ?>
        </p>
        <div class="riga-azioni">
          <?php foreach ([['automatica', 'cambia', 'cercane un\'altra'],
                          ['togli', 'scarta', 'togli']] as [$che, $ic, $et]): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="che" value="<?= $che ?>">
              <button class="bottone bottone-tenue piccolo" type="submit">
                <?= icona($ic) ?><?= $et ?></button>
            </form>
          <?php endforeach ?>
        </div>
      </div>
    <?php else: ?>
      <div class="copertina-vuota"><?= icona('immagine', 30) ?><span>nessuna copertina</span></div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="che" value="automatica">
        <button class="bottone" type="submit"><?= icona('cambia') ?>cercane una</button>
      </form>
    <?php endif ?>
  </div>

  <?php /* Il catalogo sta chiuso dietro un pulsante: chi apre la pagina
           di solito vuole vedere la copertina che c'è, e sessanta
           miniature sotto la spingono fuori dallo schermo. Si apre da sé
           solo quando si torna da un filtro, perché lì le foto sono
           proprio quello che si stava cercando. */ ?>
  <details class="caricamento scegli-foto" id="catalogo"<?= $cerca !== '' ? ' open' : '' ?>>
    <summary><?= icona('immagine') ?> oppure scegline una dal catalogo</summary>

  <p class="occhiello">
    Le fotografie del catalogo, con licenza libera. Vengono prima quelle
    di <strong><?= e($mira) ?></strong>, che è il soggetto di questo articolo, e
    quelle usate meno. Scegliendone una a mano il programma non la
    cambierà più da solo.
  </p>

  <?php /* Due filtri in uno. Mentre si scrive si nascondono le foto già
           in pagina che non c'entrano, senza ricaricare; con Invio si
           cerca nel catalogo intero, che è più delle sessanta mostrate. */ ?>
  <form class="ricerca-blocco" method="get" action="#catalogo">
    <div class="ricerca">
      <input type="search" name="q" value="<?= e($cerca) ?>" id="filtro-foto"
             placeholder="filtra per autore, titolo o soggetto…" autocomplete="off">
      <button type="submit">filtra</button>
    </div>
  </form>

  <?php if (!$foto): ?>
    <p class="vuoto">Nessuna fotografia con questo filtro.</p>
  <?php else: ?>
    <p class="vuoto" id="filtro-vuoto" hidden>Nessuna di queste: premi Invio per cercare in tutto il catalogo.</p>
    <div class="griglia-foto">
      <?php foreach ($foto as $f): ?>
        <form method="post" class="foto-scelta"
              data-testo="<?= e(mb_strtolower(implode(' ', [$f['titolo'] ?? '', $f['autore'] ?? '',
                  $f['soggetto'] ?? '', $f['riferimento'] ?? '']))) ?>">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="che" value="scelta">
          <input type="hidden" name="img" value="<?= (int)$f['id'] ?>">
          <?php /* La miniatura si chiede a Wikimedia a 320 pixel invece di
                   scaricare l'originale a 1200: sessanta immagini a piena
                   misura sarebbero dodici megabyte per aprire una pagina. */ ?>
          <button type="submit" title="usa questa">
            <img src="<?= e(miniaturaFoto((string)$f['url_file'])) ?>"
                 alt="" loading="lazy" decoding="async">
            <span class="foto-dati">
              <span class="foto-autore"><?= e(mb_substr((string)($f['autore'] ?: '—'), 0, 34)) ?></span>
              <span class="foto-uso">
                <?php /* Commons non sempre sa il giorno: si mostra quello
                         che sa. Sapere che una foto è del 2009 evita di
                         metterla su una notizia di oggi. */ ?>
                <?php if ($d = didascaliaFoto($f, 30)): ?>
                  <span class="foto-quando"><?= e($d) ?></span> ·
                <?php endif ?>
                <?= (int)$f['usata'] ?> <?= (int)$f['usata'] === 1 ? 'uso' : 'usi' ?>
                <?php if ($f['soggetto'] !== 'band'): ?> · <?= e($f['soggetto']) ?><?php endif ?>
                <?php /* Da dove viene: cambia dove punta il credito, e
                         quindi dove va chi vuole vedere l'originale. */ ?>
                · <?= e((string)($f['provenienza'] ?? 'commons')) ?>
                <?php /* Una NC si vede: se un giorno il sito diventasse
                           commerciale, sono queste le foto da togliere. */ ?>
                <?php if (preg_match('/\bnc\b/i', (string)$f['licenza'])): ?>
                  <span class="tag-nc">NC</span>
                <?php endif ?>
              </span>
            </span>
          </button>
        </form>
      <?php endforeach ?>
    </div>
  <?php endif ?>
  </details>
</div>

<script>
(function () {
  var pannello = document.getElementById('catalogo');
  var campo = document.getElementById('filtro-foto');
  var vuoto = document.getElementById('filtro-vuoto');
  var foto = document.querySelectorAll('.griglia-foto .foto-scelta');
  if (!pannello || !campo) return;

  // Aperto il pannello, si può scrivere subito.
  pannello.addEventListener('toggle', function () {
    if (pannello.open) campo.focus();
  });

  campo.addEventListener('input', function () {
    var parole = campo.value.toLowerCase().split(/\s+/).filter(Boolean);
    var viste = 0;
    foto.forEach(function (f) {
      var t = f.getAttribute('data-testo') || '';
      var si = parole.every(function (p) { return t.indexOf(p) !== -1; });
      f.hidden = !si;
      if (si) viste++;
    });
    if (vuoto) vuoto.hidden = viste > 0 || foto.length === 0;
  });
})();
</script>
