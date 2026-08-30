<?php
/**
 * Le schede di un elenco, nude: nessun contenitore, nessun titolo.
 *
 * Sta in un file suo perché lo rendono in due modi diversi — la pagina
 * intera e il pezzo che arriva col "carica altro" — e due copie dello
 * stesso markup sarebbero divergute alla prima modifica.
 */
?>
<?php foreach ($articoli as $a): ?>
  <article class="scheda">
    <div class="meta">
      <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
      <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(quandoIt($a['pubblicato_il'])) ?></time>
      <?= etichettaHot($a['rilevanza'] ?? null) ?>
    </div>
    <h2><a href="<?= u('notizie/' . $a['slug'] . '/') ?>"><?= e($a['titolo_it']) ?></a></h2>
    <p class="sommario"><?= e(mb_substr($a['sommario_it'], 0, 170)) ?>…</p>
    <div class="piede">
      <p class="fonte"><?= $a['fonte_nome'] ? e($a['fonte_nome']) : bollino() ?></p>
      <?= condividiMini($a['slug'], $a['titolo_it']) ?>
    </div>
  </article>
<?php endforeach ?>
