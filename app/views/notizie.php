<h1 class="titolo-sezione">Notizie</h1>

<?php
/** L'indirizzo di questa pagina cambiando un filtro solo. */
$conFiltro = function (array $cambia) use ($anno, $cat): string {
    $q = array_filter(['anno' => $anno, 'cat' => $cat] + [], fn($v) => $v !== null && $v !== '');
    foreach ($cambia as $k => $v) {
        if ($v === null) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    return u('notizie') . ($q ? '?' . http_build_query($q) : '');
};
?>

<div class="filtri-archivio">
  <div class="filtro-riga">
    <span class="filtro-nome">categoria</span>
    <a class="filtro-voce<?= $cat === null ? ' attivo' : '' ?>" href="<?= e($conFiltro(['cat' => null])) ?>">tutte</a>
    <?php foreach ($categorie as $c): ?>
      <a class="filtro-voce<?= $cat === $c['categoria'] ? ' attivo' : '' ?>"
         href="<?= e($conFiltro(['cat' => $c['categoria']])) ?>"><?= e($c['categoria']) ?>
        <span class="filtro-n"><?= (int)$c['quanti'] ?></span></a>
    <?php endforeach ?>
  </div>

  <div class="filtro-riga">
    <span class="filtro-nome">anno</span>
    <a class="filtro-voce<?= $anno === null ? ' attivo' : '' ?>" href="<?= e($conFiltro(['anno' => null])) ?>">tutti</a>
    <?php foreach ($anni as $y): ?>
      <a class="filtro-voce<?= $anno === (int)$y['anno'] ? ' attivo' : '' ?>"
         href="<?= e($conFiltro(['anno' => (int)$y['anno']])) ?>"><?= (int)$y['anno'] ?>
        <span class="filtro-n"><?= (int)$y['quanti'] ?></span></a>
    <?php endforeach ?>
  </div>
</div>

<?php if (!$totale): ?>
  <p class="vuoto">Nessun articolo con questi filtri.</p>
<?php else: ?>
  <p class="occhiello conteggio">
    <?= $totale ?><?= $totale === 1 ? ' articolo' : ' articoli' ?><?php
      if ($cat)  { echo ' in ', e($cat); }
      if ($anno) { echo ' nel ', (int)$anno; }
    ?>.
  </p>

  <div class="elenco elenco-largo" id="elenco-notizie">
    <?php require __DIR__ . '/schede.php'; ?>
  </div>

  <?php if ($altraPagina): ?>
    <?php /* Un collegamento vero, non un pulsante: senza JavaScript porta
             alla pagina successiva, e un motore di ricerca lo può seguire.
             Lo script lo trasforma in "carica altro" e gli fa aggiungere
             le schede qui sotto invece di cambiare pagina. */ ?>
    <div class="altro">
      <a class="bottone-altro" href="<?= e($altraPagina) ?>" rel="next"
         data-per-pagina="<?= (int)$perPagina ?>">carica altro</a>
    </div>
  <?php endif ?>
<?php endif ?>
