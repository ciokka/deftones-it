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
<link rel="stylesheet" href="<?= u('assets/stile.css') ?>?v=3">
</head>
<body>

<header class="testata">
  <div class="contenitore testata-int">
    <a class="marchio" href="<?= u('/') ?>"><?php require __DIR__ . '/logo.php'; ?></a>
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

</body>
</html>
