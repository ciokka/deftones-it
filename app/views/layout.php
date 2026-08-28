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
<link rel="stylesheet" href="<?= u('assets/stile.css') ?>?v=16">
</head>
<body>

<?php /* Lo sfondo: tre copie dello stesso pattern a scale diverse che
         scorrono a velocità diverse. Fuori dal flusso e inerte. */ ?>
<div class="sfondo" aria-hidden="true">
  <?php /* Le 123 lettere del pattern sono divise in tre file — 48, 45 e
           30 — distribuite a rotazione 3-3-2, così lettere adiacenti
           finiscono sempre in gruppi diversi. Stessa scala e stessa
           posizione: sovrapposti ricompongono il disegno originale, ma
           scorrendo si separano. data-scala serve al JS per sapere
           quanto è alta una piastrella. */ ?>
  <span class="strato s1" data-v="0.08" data-scala="150"></span>
  <span class="strato s2" data-v="0.45" data-scala="150"></span>
  <span class="strato s3" data-v="1.10" data-scala="150"></span>
</div>

<?php
// Le voci del menu, definite una volta sola: vengono rese due volte —
// dentro la testata su desktop, come barra a sé su mobile — perché un
// elemento sticky smette di stare fermo quando il suo contenitore esce
// dallo schermo, e su mobile la testata deve scorrere via.
$voci = [
    u('/')                  => 'Notizie',
    u('categoria/tour/')    => 'Tour',
    u('feed.xml')           => 'RSS',
];
?>
<header class="testata">
  <div class="contenitore testata-int">
    <div class="marchio-blocco">
      <a class="marchio" href="<?= u('/') ?>"><?php require __DIR__ . '/logo.php'; ?></a>
      <p class="sottotitolo">The Italian Deftones fan site <span>since 2002</span></p>
    </div>
    <nav class="menu menu-riga">
      <?php foreach ($voci as $href => $et): ?><a href="<?= $href ?>"><?= $et ?></a><?php endforeach ?>
    </nav>
  </div>
</header>

<nav class="menu-barra">
  <div class="contenitore menu">
    <?php foreach ($voci as $href => $et): ?><a href="<?= $href ?>"><?= $et ?></a><?php endforeach ?>
  </div>
</nav>

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
// Parallasse dello sfondo: i tre strati si muovono a velocità diverse,
// così le lettere del pattern scorrono l'una sull'altra invece di stare
// ferme. Si aggiorna una volta per fotogramma e usa translate3d, che il
// browser compone sulla GPU senza ridisegnare la pagina.
(function () {
  // Va controllato "reduce", non "no-preference": un browser che non
  // conoscesse la query risponderebbe falso a entrambe, e chiedendo
  // "no-preference" l'animazione resterebbe spenta senza motivo.
  var fermo = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
  if (fermo && fermo.matches) { return; }

  var strati = [].slice.call(document.querySelectorAll('.strato'));
  if (!strati.length) { return; }
  var inCorso = false;

  // Proporzioni del disegno: 2096 x 2705.81. Servono per sapere quanto è
  // alta una piastrella una volta scalata, e quindi ogni quanto lo strato
  // deve ricominciare da capo.
  var PROPORZIONE = 2705.81 / 2096;

  function misura() {
    for (var i = 0; i < strati.length; i++) {
      var el = strati[i];
      var scala = parseFloat(el.getAttribute('data-scala')) || 100;
      el.style.backgroundSize = scala + '% auto';
      el.passo = el.clientWidth * (scala / 100) * PROPORZIONE;
    }
  }

  function aggiorna() {
    var y = window.scrollY;
    if (y === undefined) { y = (document.documentElement || document.body).scrollTop || 0; }
    for (var i = 0; i < strati.length; i++) {
      var el = strati[i];
      var d = -y * (parseFloat(el.getAttribute('data-v')) || 0);
      // Il resto della divisione riporta lo spostamento dentro l'altezza
      // di una piastrella: lo strato scorre all'infinito invece di
      // scappare fuori schermo e sparire.
      if (el.passo > 0) { d = d % el.passo; }
      el.style.transform = 'translate3d(0,' + d.toFixed(2) + 'px,0)';
    }
    inCorso = false;
  }

  function chiedi() {
    if (!inCorso) { inCorso = true; requestAnimationFrame(aggiorna); }
  }
  window.addEventListener('scroll', chiedi, { passive: true });
  window.addEventListener('resize', function () { misura(); chiedi(); }, { passive: true });

  misura();
  aggiorna();
})();

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
