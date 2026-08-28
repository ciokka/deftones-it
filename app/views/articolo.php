<article class="articolo">
  <div class="meta">
    <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
    <?php if ($a['attendibilita'] !== 'confermato'): ?>
      <span class="etichetta et-dubbio"><?= e($a['attendibilita']) ?></span>
    <?php endif ?>
    <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(dataIt($a['pubblicato_il'])) ?></time>
  </div>

  <h1><?= e($a['titolo_it']) ?></h1>

  <?php if ($a['attendibilita'] !== 'confermato'): ?>
    <p class="nota-dubbio">
      Notizia non confermata da fonti ufficiali. Riportata così come circola.
    </p>
  <?php endif ?>

  <div class="corpo">
    <?php if (!empty($a['corpo_it'])): ?>
      <?php /* Articoli dell'archivio: il sommario è solo un estratto del
               corpo, ripeterlo in cima sarebbe una doppione. */ ?>
      <?= $a['corpo_it'] ?>
    <?php else: ?>
      <p><?= nl2br(e($a['sommario_it'])) ?></p>
    <?php endif ?>
  </div>

  <?php if ($a['fonte_url']): ?>
  <p class="rimando">
    Questo è un riassunto. L'articolo originale è di
    <strong><?= e($a['fonte_nome']) ?></strong>:
    <a href="<?= e($a['fonte_url']) ?>" target="_blank" rel="noopener">leggilo lì <span aria-hidden="true">→</span></a>
  </p>
  <?php endif ?>

  <?php $tag = json_decode((string)$a['tag'], true) ?: []; ?>
  <?php if ($tag): ?>
  <p class="tag">
    <?php foreach ($tag as $tg): ?>
      <a href="<?= u('tag/' . rawurlencode($tg) . '/') ?>">#<?= e($tg) ?></a>
    <?php endforeach ?>
  </p>
  <?php endif ?>
</article>

<?php if ($altri): ?>
<section class="correlate">
  <h2>Altre notizie</h2>
  <div class="elenco">
    <?php foreach ($altri as $x): ?>
      <article class="scheda">
        <div class="meta"><time><?= e(quandoIt($x['pubblicato_il'])) ?></time></div>
        <h2><a href="<?= u('notizie/' . $x['slug'] . '/') ?>"><?= e($x['titolo_it']) ?></a></h2>
      </article>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>
