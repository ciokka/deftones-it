<h1 class="titolo-sezione">Cerca</h1>

<div class="ricerca-blocco">
  <form class="ricerca" method="get" action="<?= u('cerca') ?>" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" class="ricerca-campo"
           autocomplete="off" placeholder="un nome, un disco, una città…"
           aria-label="Cerca negli articoli" autofocus>
    <button type="submit">cerca</button>
  </form>
  <ul class="suggerimenti" hidden></ul>
</div>

<?php if ($errore): ?>
  <p class="vuoto"><?= e($errore) ?></p>

<?php elseif ($q === ''): ?>
  <p class="occhiello">
    Vent'anni di archivio più le notizie di oggi. La ricerca guarda dentro
    al testo intero degli articoli, non solo nei titoli: puoi cercare il
    nome di una città, di un disco o di una persona.
  </p>

<?php elseif (!$articoli): ?>
  <p class="vuoto">Nessun articolo per «<?= e($q) ?>».</p>
  <p class="occhiello">
    Le parole devono esserci tutte. Se hai scritto più di un termine,
    prova a toglierne uno.
  </p>

<?php else: ?>
  <p class="occhiello">
    <?= count($articoli) >= 60 ? 'I primi 60 articoli' : count($articoli)
        . (count($articoli) === 1 ? ' articolo' : ' articoli') ?>
    per «<?= e($q) ?>», dal più pertinente.
  </p>
  <?php $intestazione = ''; require __DIR__ . '/lista.php'; ?>
<?php endif ?>
