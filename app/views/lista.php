<h1 class="titolo-sezione"><?= e($intestazione) ?></h1>

<?php if (!$articoli): ?>
  <p class="vuoto">Nessuna notizia in questa sezione.</p>
<?php else: ?>
  <div class="elenco elenco-largo">
    <?php foreach ($articoli as $a): ?>
      <article class="scheda">
        <div class="meta">
          <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
          <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(quandoIt($a['pubblicato_il'])) ?></time>
        </div>
        <h2><a href="<?= u('notizie/' . $a['slug'] . '/') ?>"><?= e($a['titolo_it']) ?></a></h2>
        <p class="sommario"><?= e(mb_substr($a['sommario_it'], 0, 170)) ?>…</p>
        <div class="piede">
          <p class="fonte"><?= $a['fonte_nome'] ? e($a['fonte_nome']) : bollino() ?></p>
          <?= condividiMini($a['slug'], $a['titolo_it']) ?>
        </div>
      </article>
    <?php endforeach ?>
  </div>
<?php endif ?>
