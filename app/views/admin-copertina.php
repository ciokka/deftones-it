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

  <h2 class="titoletto">Oppure scegline una</h2>
  <p class="occhiello">
    Le fotografie del catalogo, con licenza libera. Vengono prima quelle
    di <strong><?= e($mira) ?></strong>, che è il soggetto di questo articolo, e
    quelle usate meno. Scegliendone una a mano il programma non la
    cambierà più da solo.
  </p>

  <form class="ricerca-blocco" method="get">
    <div class="ricerca">
      <input type="search" name="q" value="<?= e($cerca) ?>"
             placeholder="filtra per autore o per titolo del file…">
      <button type="submit">filtra</button>
    </div>
  </form>

  <?php if (!$foto): ?>
    <p class="vuoto">Nessuna fotografia con questo filtro.</p>
  <?php else: ?>
    <div class="griglia-foto">
      <?php foreach ($foto as $f): ?>
        <form method="post" class="foto-scelta">
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
                <?php if ($f['data_foto']): ?>
                  <span class="foto-quando"><?= e(dataFotoBreve((string)$f['data_foto'])) ?></span> ·
                <?php elseif (!empty($f['titolo'])): ?>
                  <?php /* Le foto di Flickr non hanno data: Openverse non
                           la dà. Il titolo però spesso dice l'occasione,
                           che per scegliere vale altrettanto. */ ?>
                  <span class="foto-quando"><?= e(mb_substr((string)$f['titolo'], 0, 30)) ?></span> ·
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
</div>
