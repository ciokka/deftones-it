<h1 class="titolo-sezione">raccolte</h1>

<p class="occhiello">
  Vent'anni di archivio contengono storie raccontate a puntate. Qui sono
  rimesse in fila, dalla prima notizia all'ultima.
</p>

<?php if (!$raccolte): ?>
  <p class="vuoto">Nessuna raccolta pubblicata.</p>
<?php else: ?>
  <div class="elenco">
    <?php foreach ($raccolte as $r): ?>
      <article class="scheda">
        <div class="meta"><span><?= (int)$r['quanti'] ?> articoli</span></div>
        <h2><a href="<?= u('raccolte/' . $r['slug'] . '/') ?>"><?= e($r['titolo']) ?></a></h2>
        <p class="sommario"><?= e($r['sottotitolo']) ?></p>
      </article>
    <?php endforeach ?>
  </div>
<?php endif ?>
