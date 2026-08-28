<?php if (!$articoli): ?>
  <div class="vuoto">
    <h1>Ancora niente</h1>
    <p>Le prime notizie compariranno appena verranno approvate.</p>
  </div>
<?php else: ?>

  <?php $primo = array_shift($articoli); ?>
  <article class="apertura">
    <div class="meta">
      <span class="etichetta et-<?= e($primo['categoria']) ?>"><?= e($primo['categoria']) ?></span>
      <?php if ($primo['attendibilita'] !== 'confermato'): ?>
        <span class="etichetta et-dubbio"><?= e($primo['attendibilita']) ?></span>
      <?php endif ?>
      <time datetime="<?= e($primo['pubblicato_il']) ?>"><?= e(quandoIt($primo['pubblicato_il'])) ?></time>
    </div>
    <h1><a href="<?= u('notizie/' . $primo['slug'] . '/') ?>"><?= e($primo['titolo_it']) ?></a></h1>
    <p class="sommario"><?= e(mb_substr($primo['sommario_it'], 0, 260)) ?><?= mb_strlen($primo['sommario_it']) > 260 ? '…' : '' ?></p>
    <p class="fonte">Fonte: <?= e($primo['fonte_nome']) ?></p>
  </article>

  <?php if ($articoli): ?>
  <div class="elenco">
    <?php foreach ($articoli as $a): ?>
      <article class="scheda">
        <div class="meta">
          <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
          <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(quandoIt($a['pubblicato_il'])) ?></time>
        </div>
        <h2><a href="<?= u('notizie/' . $a['slug'] . '/') ?>"><?= e($a['titolo_it']) ?></a></h2>
        <p class="sommario"><?= e(mb_substr($a['sommario_it'], 0, 150)) ?>…</p>
        <p class="fonte"><?= e($a['fonte_nome']) ?></p>
      </article>
    <?php endforeach ?>
  </div>
  <?php endif ?>

<?php endif ?>
