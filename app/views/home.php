<?php if (!$articoli): ?>
  <div class="vuoto">
    <h1>Ancora niente</h1>
    <p>Le prime notizie compariranno appena verranno approvate.</p>
  </div>
<?php else: ?>

  <?php $primo = array_shift($articoli); ?>
  <article class="apertura">
    <?php /* Qui invece la foto viene prima: in home è lei l'aggancio,
             il titolo lo si legge dopo essersi fermati. */ ?>
    <a class="copertina-link" href="<?= u('notizie/' . $primo['slug'] . '/') ?>"
       tabindex="-1" aria-hidden="true"><?= copertina($primo, true) ?></a>
    <div class="meta">
      <span class="etichetta et-<?= e($primo['categoria']) ?>"><?= e($primo['categoria']) ?></span>
      <?php if ($primo['attendibilita'] !== 'confermato'): ?>
        <span class="etichetta et-dubbio"><?= e($primo['attendibilita']) ?></span>
      <?php endif ?>
      <time datetime="<?= e($primo['pubblicato_il']) ?>"><?= e(quandoIt($primo['pubblicato_il'])) ?></time>
    </div>
    <h1><a href="<?= u('notizie/' . $primo['slug'] . '/') ?>"><?= e($primo['titolo_it']) ?></a></h1>
    <p class="sommario"><?= e(mb_substr($primo['sommario_it'], 0, 260)) ?><?= mb_strlen($primo['sommario_it']) > 260 ? '…' : '' ?></p>
    <div class="piede">
      <?php if ($primo['fonte_nome']): ?>
        <p class="fonte">Fonte: <?= e($primo['fonte_nome']) ?></p>
      <?php else: ?>
        <p class="fonte"><?= bollino() ?></p>
      <?php endif ?>
      <?= condividiMini($primo['slug'], $primo['titolo_it']) ?>
    </div>
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
        <div class="piede">
          <p class="fonte"><?= $a['fonte_nome'] ? e($a['fonte_nome']) : bollino() ?></p>
          <?= condividiMini($a['slug'], $a['titolo_it']) ?>
        </div>
      </article>
    <?php endforeach ?>
  </div>
  <?php endif ?>

<?php endif ?>
