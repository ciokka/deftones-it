<article class="raccolta">
  <div class="meta"><a href="<?= u('raccolte/') ?>">raccolte</a></div>

  <h1><?= e($r['titolo']) ?></h1>
  <p class="occhiello"><?= e($r['sottotitolo']) ?></p>

  <?php if ($r['introduzione']): ?>
    <div class="corpo introduzione">
      <?php foreach (preg_split('/\n\s*\n/', trim($r['introduzione'])) as $par): ?>
        <p><?= nl2br(e($par)) ?></p>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</article>

<?php if (!$articoli): ?>
  <p class="vuoto">Gli articoli di questa raccolta non sono ancora pubblicati.</p>
<?php else: ?>
  <ol class="cronologia">
    <?php $annoPrec = null; ?>
    <?php foreach ($articoli as $a): ?>
      <?php $anno = (int)substr((string)$a['pubblicato_il'], 0, 4); ?>
      <?php if ($anno !== $annoPrec): ?>
        <li class="cronologia-anno"><?= $anno ?></li>
        <?php $annoPrec = $anno; ?>
      <?php endif ?>
      <li class="cronologia-voce">
        <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(dataIt($a['pubblicato_il'])) ?></time>
        <a href="<?= u('notizie/' . $a['slug'] . '/') ?>"><?= e($a['titolo_it']) ?></a>
      </li>
    <?php endforeach ?>
  </ol>
<?php endif ?>
