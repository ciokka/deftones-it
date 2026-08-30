<?php /* Il titolo di sezione è facoltativo: la ricerca riusa questa
         vista dopo aver già scritto il suo. */ ?>
<?php if (!empty($intestazione)): ?>
<h1 class="titolo-sezione"><?= e($intestazione) ?></h1>
<?php endif ?>

<?php if (!$articoli): ?>
  <p class="vuoto">Nessuna notizia in questa sezione.</p>
<?php else: ?>
  <div class="elenco elenco-largo">
    <?php require __DIR__ . '/schede.php'; ?>
  </div>
<?php endif ?>
