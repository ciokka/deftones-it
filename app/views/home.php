<?php if (!$articoli): ?>
  <div class="vuoto">
    <h1>Ancora niente</h1>
    <p>Le prime notizie compariranno appena verranno approvate.</p>
  </div>
<?php else: ?>

  <?php $primo = array_shift($articoli); ?>
  <?php $conFoto = copertinaImg($primo, true); ?>

  <article class="apertura<?= $conFoto ? ' hero' : '' ?>">
    <?php if ($conFoto): ?>
      <?php /* Il banner: la foto riempie il riquadro, il testo ci sta
               sopra. Non avvolgo tutto in un <a> perché dentro ci va il
               credito, che è a sua volta un link e non può stare dentro
               un altro link: il collegamento lo stende il titolo, con un
               ::after che copre tutto il banner. */ ?>
      <div class="hero-foto"><?= $conFoto ?></div>
      <div class="hero-velo" aria-hidden="true"></div>
    <?php endif ?>

    <div class="hero-interno">
    <div class="hero-testo">
      <div class="meta">
        <span class="etichetta et-<?= e($primo['categoria']) ?>"><?= e($primo['categoria']) ?></span>
        <?php if ($primo['attendibilita'] !== 'confermato'): ?>
          <span class="etichetta et-dubbio"><?= e($primo['attendibilita']) ?></span>
        <?php endif ?>
        <time datetime="<?= e($primo['pubblicato_il']) ?>"><?= e(quandoIt($primo['pubblicato_il'])) ?></time>
        <?= etichettaHot($primo['rilevanza'] ?? null) ?>
      </div>
      <h1><a href="<?= u('notizie/' . $primo['slug'] . '/') ?>"><?= e($primo['titolo_it']) ?></a></h1>
      <p class="sommario"><?= e(mb_substr($primo['sommario_it'], 0, 260)) ?><?= mb_strlen($primo['sommario_it']) > 260 ? '…' : '' ?></p>
    </div>

    <div class="piede">
      <?php if ($primo['fonte_nome']): ?>
        <p class="fonte">Fonte: <?= e($primo['fonte_nome']) ?></p>
      <?php else: ?>
        <p class="fonte"><?= bollino() ?></p>
      <?php endif ?>
      <div class="piede-destra">
        <?= creditoImmagine($primo, 'p') ?>
        <?= condividiMini($primo['slug'], $primo['titolo_it']) ?>
      </div>
    </div>
    </div>
  </article>

  <?php if ($articoli): ?>
  <div class="elenco">
    <?php $taglio = 150; require __DIR__ . '/schede.php'; ?>
  </div>
  <?php endif ?>

<?php endif ?>
