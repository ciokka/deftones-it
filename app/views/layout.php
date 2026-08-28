<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titolo) ?></title>
<?php if ($descrizione): ?>
<meta name="description" content="<?= e($descrizione) ?>">
<?php endif ?>
<?php if ($canonico): ?>
<link rel="canonical" href="<?= e($canonico) ?>">
<?php endif ?>
<meta property="og:title" content="<?= e($titolo) ?>">
<meta property="og:description" content="<?= e($descrizione) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(cfg('site_name')) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= e(cfg('site_name')) ?>" href="<?= u('feed.xml') ?>">
<link rel="stylesheet" href="<?= u('assets/stile.css') ?>?v=10">
</head>
<body>

<header class="testata">
  <div class="contenitore testata-int">
    <div class="marchio-blocco">
      <a class="marchio" href="<?= u('/') ?>"><?php require __DIR__ . '/logo.php'; ?></a>
      <p class="sottotitolo">The Italian Deftones fan site <span>since 2002</span></p>
    </div>
    <nav class="menu">
      <a href="<?= u('/') ?>">Notizie</a>
      <a href="<?= u('categoria/tour/') ?>">Tour</a>
      <a href="<?= u('feed.xml') ?>">RSS</a>
    </nav>
  </div>
</header>

<main class="contenitore">
<?= $contenuto ?>
</main>

<footer class="pieDiPagina">
  <div class="contenitore">
    <p class="avviso">
      Sito non ufficiale gestito da fan. Non affiliato ai Deftones, al loro
      management o alle etichette discografiche. Le notizie sono riassunti
      redazionali con link alla fonte originale, che resta di chi l'ha scritta.
    </p>
    <p class="colofone">
      <?= date('Y') ?> · <a href="<?= u('feed.xml') ?>">RSS</a>
    </p>
  </div>
</footer>

<script>
// Un'immagine che non carica lascerebbe l'icona rotta del browser.
// Un ascoltatore solo, in fase di cattura perché "error" non risale,
// la sostituisce con un segnaposto coerente col resto del sito.
// Vale anche per le immagini dell'archivio 2002-2011, i cui file oggi
// non ci sono: il giorno che salteranno fuori, ripartiranno da sole.
(function () {
  var segnaposto = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 360">' +
    '<rect width="640" height="360" fill="#0c0c0c"/>' +
    '<rect x="0.5" y="0.5" width="639" height="359" fill="none" stroke="#262629"/>' +
    '<text x="320" y="176" fill="#5a5856" font-family="system-ui,sans-serif" ' +
    'font-size="17" text-anchor="middle">immagine non disponibile</text>' +
    '<text x="320" y="202" fill="#403f3d" font-family="system-ui,sans-serif" ' +
    'font-size="13" text-anchor="middle">archivio 2002-2011</text></svg>'
  );
  document.addEventListener('error', function (e) {
    var t = e.target;
    if (t && t.tagName === 'IMG' && !t.dataset.mancante) {
      t.dataset.mancante = '1';      // evita il ciclo se anche questo fallisse
      t.classList.add('img-mancante');
      t.src = segnaposto;
    }
  }, true);
})();
</script>

</body>
</html>
