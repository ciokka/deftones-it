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
<meta property="og:locale" content="it_IT">
<?php if ($canonico): ?>
<meta property="og:url" content="<?= e($canonico) ?>">
<?php endif ?>
<meta property="og:image" content="<?= e($immagine) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="675">
<meta property="og:image:alt" content="deftones.it — the italian Deftones family">
<?php /* summary_large_image è ciò che fa mostrare l'immagine grande
         invece della miniatura quadrata di fianco al testo. */ ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="alternate" type="application/rss+xml" title="<?= e(cfg('site_name')) ?>" href="<?= u('feed.xml') ?>">
<link rel="stylesheet" href="<?= u('assets/stile.css') ?>?v=37">
</head>
<body>

<?php /* Lo sfondo: tre copie dello stesso pattern a scale diverse che
         scorrono a velocità diverse. Fuori dal flusso e inerte. */ ?>
<div class="sfondo" aria-hidden="true">
  <?php
  /* Quattro piani, generati da strumenti/genera-sfondo.py dalle sette
     lettere di materiali/lettere.svg.

     La scala è la STESSA per tutti: la profondità viene dall'altezza
     delle lettere dentro la piastrella, non dall'ingrandimento dello
     strato. Se le scale differissero, l'ingrandimento moltiplicherebbe
     anche lo spessore del contorno e il piano vicino sembrerebbe
     disegnato con un pennarello più grosso. */
  // scala = quanto è larga la piastrella rispetto al contenitore.
  // Su mobile è molto maggiore: lo schermo è stretto, e senza questo le
  // lettere risulterebbero minuscole e troppo fitte.
  $piani = [
      ['v' => 0.10, 'scala' => 90, 'scalaMobile' => 300],
      ['v' => 0.32, 'scala' => 90, 'scalaMobile' => 300],
      ['v' => 0.62, 'scala' => 90, 'scalaMobile' => 300],
      ['v' => 1.00, 'scala' => 90, 'scalaMobile' => 300],
  ];
  foreach ($piani as $i => $pn):
  ?>
    <span class="strato s<?= $i + 1 ?>" data-v="<?= $pn['v'] ?>"
          data-scala="<?= $pn['scala'] ?>" data-scala-mobile="<?= $pn['scalaMobile'] ?>"></span>
  <?php endforeach ?>
</div>

<?php
// Le voci del menu, definite una volta sola: vengono rese due volte —
// dentro la testata su desktop, come barra a sé su mobile — perché un
// elemento sticky smette di stare fermo quando il suo contenitore esce
// dallo schermo, e su mobile la testata deve scorrere via.
$voci = [
    u('/')                  => 'Notizie',
    u('raccolte/')          => 'Raccolte',
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
      <?php foreach ($voci as $href => $et): ?><a href="<?= $href ?>"><?= $et ?></a><?php endforeach ?><a class="menu-lente" href="<?= u('cerca') ?>" aria-label="Cerca" title="Cerca"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="7" cy="7" r="4.6"/><path d="M10.5 10.5 14 14"/></svg></a>
    </nav>
  </div>
</header>

<?php /* Il cercatore vive nascosto in ogni pagina: la lente del menu lo
         apre senza cambiare pagina. Senza JavaScript la lente resta un
         link a /cerca, che funziona da sola. */ ?>
<div class="cercatore" hidden>
  <div class="cercatore-fondo"></div>
  <div class="cercatore-riquadro ricerca-blocco" role="dialog" aria-modal="true" aria-label="Cerca">
    <form class="ricerca" method="get" action="<?= u('cerca') ?>" role="search">
      <input type="search" name="q" class="ricerca-campo" autocomplete="off"
             placeholder="un nome, un disco, una città…" aria-label="Cerca negli articoli">
      <button type="submit">cerca</button>
    </form>
    <ul class="suggerimenti" hidden></ul>
  </div>
</div>

<nav class="menu-barra">
  <div class="contenitore menu">
    <?php foreach ($voci as $href => $et): ?><a href="<?= $href ?>"><?= $et ?></a><?php endforeach ?><a class="menu-lente" href="<?= u('cerca') ?>" aria-label="Cerca" title="Cerca"><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><circle cx="7" cy="7" r="4.6"/><path d="M10.5 10.5 14 14"/></svg></a>
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

  // Le piastrelle degli strati sono quadrate (1000x1000), quindi la loro
  // altezza sullo schermo è pari alla larghezza. Serve a sapere ogni
  // quanto lo strato deve ricominciare da capo.
  var PROPORZIONE = 1;

  function misura() {
    var stretto = window.innerWidth < 640;
    var alt = window.innerHeight;
    for (var i = 0; i < strati.length; i++) {
      var el = strati[i];
      var attr = stretto ? 'data-scala-mobile' : 'data-scala';
      var scala = parseFloat(el.getAttribute(attr)) || 100;
      el.style.backgroundSize = scala + '% auto';
      el.passo = el.parentNode.clientWidth * (scala / 100) * PROPORZIONE;

      // Lo strato dev'essere alto almeno quanto la finestra PIÙ una
      // piastrella: scorrendo si sposta verso l'alto di una piastrella
      // intera prima di ricominciare, e se fosse più basso il suo bordo
      // inferiore entrerebbe in campo — un taglio netto in mezzo al
      // disegno. Su desktop la piastrella è più alta della finestra,
      // quindi non basta una percentuale fissa.
      el.style.height = (alt + el.passo + 4) + 'px';
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

// Condivisione. Su telefono il sistema operativo ha già il suo
// pannello — è più comodo di qualsiasi elenco di icone e conosce le app
// installate — quindi se il browser lo espone sostituiamo tutto con un
// pulsante solo. Su desktop restano i link, che nessun sistema offre.
(function () {
  var box = document.querySelector('.condividi');
  if (!box) { return; }

  var indirizzo = box.getAttribute('data-url');
  var titolo = box.getAttribute('data-titolo');

  if (navigator.share) {
    var voci = box.querySelectorAll('.condividi-voce');
    for (var i = 0; i < voci.length; i++) { voci[i].remove(); }
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'condividi-voce';
    b.textContent = 'condividi';
    b.addEventListener('click', function () {
      navigator.share({ title: titolo, url: indirizzo }).catch(function () {});
    });
    box.appendChild(b);
    return;
  }

  var copia = box.querySelector('.condividi-copia');
  if (copia && navigator.clipboard) {
    copia.addEventListener('click', function () {
      navigator.clipboard.writeText(indirizzo).then(function () {
        var prima = copia.textContent;
        copia.textContent = 'copiato';
        copia.classList.add('fatto');
        setTimeout(function () {
          copia.textContent = prima;
          copia.classList.remove('fatto');
        }, 1800);
      }).catch(function () {});
    });
  } else if (copia) {
    copia.remove();          // senza appunti il pulsante non farebbe nulla
  }
})();

// Le schede degli elenchi hanno un pulsante solo e muto: dove il browser
// espone il pannello di sistema lo apre, dove non lo espone copia
// l'indirizzo. Se non sa fare né l'una né l'altra cosa il pulsante
// sparisce, invece di restare lì a non fare niente.
(function () {
  if (!navigator.share && !navigator.clipboard) {
    var muti = document.querySelectorAll('.condividi-mini');
    for (var i = 0; i < muti.length; i++) { muti[i].remove(); }
    return;
  }
  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('.condividi-mini') : null;
    if (!b) { return; }
    var dati = { title: b.getAttribute('data-titolo'), url: b.getAttribute('data-url') };
    if (navigator.share) { navigator.share(dati).catch(function () {}); return; }
    navigator.clipboard.writeText(dati.url).then(function () {
      b.classList.add('fatto');
      setTimeout(function () { b.classList.remove('fatto'); }, 1600);
    }).catch(function () {});
  });
})();

// La ricerca. La lente del menu apre un riquadro senza cambiare pagina, e
// mentre scrivi arrivano i titoli che corrispondono. Senza JavaScript la
// lente resta un link a /cerca, che fa la stessa cosa in una pagina
// intera: questo è un miglioramento, non un requisito.
(function () {
  if (!window.fetch) { return; }
  var indirizzo = <?= json_encode(u('cerca.json')) ?>;

  function collega(blocco) {
    var campo = blocco.querySelector('.ricerca-campo');
    var elenco = blocco.querySelector('.suggerimenti');
    if (!campo || !elenco) { return; }
    var attesa = null, scelto = -1, voci = [];

    function spegni() { elenco.hidden = true; elenco.textContent = ''; voci = []; scelto = -1; }

    function evidenzia(n) {
      var figli = elenco.children;
      if (!figli.length) { return; }
      if (scelto >= 0 && figli[scelto]) { figli[scelto].classList.remove('scelto'); }
      scelto = (n + figli.length) % figli.length;
      figli[scelto].classList.add('scelto');
      figli[scelto].scrollIntoView({ block: 'nearest' });
    }

    function disegna(dati) {
      if (!dati || !dati.length) { spegni(); return; }
      elenco.textContent = '';
      dati.forEach(function (v) {
        // textContent e non innerHTML: i titoli vengono dal database e
        // qui non passano da nessun escape di PHP.
        var li = document.createElement('li');
        var a = document.createElement('a');
        a.href = v.u;
        var t = document.createElement('span');
        t.className = 'sug-titolo';
        t.textContent = v.t;
        var m = document.createElement('span');
        m.className = 'sug-meta';
        m.textContent = v.c + ' · ' + v.d;
        a.appendChild(t); a.appendChild(m); li.appendChild(a);
        elenco.appendChild(li);
      });
      voci = dati; scelto = -1; elenco.hidden = false;
    }

    campo.addEventListener('input', function () {
      var q = campo.value.trim();
      clearTimeout(attesa);
      // Sotto le tre lettere MySQL non indicizza: chiedere sarebbe
      // chiedere a vuoto.
      if (q.length < 3) { spegni(); return; }
      // Un quinto di secondo di pausa fra un tasto e l'altro: senza,
      // scrivere una parola di otto lettere sono otto ricerche a testo
      // pieno, sette delle quali già superate quando arriva la risposta.
      attesa = setTimeout(function () {
        fetch(indirizzo + '?q=' + encodeURIComponent(q))
          .then(function (r) { return r.ok ? r.json() : []; })
          .then(disegna)
          .catch(spegni);
      }, 220);
    });

    campo.addEventListener('keydown', function (e) {
      if (elenco.hidden) { return; }
      if (e.key === 'ArrowDown')      { e.preventDefault(); evidenzia(scelto + 1); }
      else if (e.key === 'ArrowUp')   { e.preventDefault(); evidenzia(scelto - 1); }
      else if (e.key === 'Enter' && scelto >= 0) { e.preventDefault(); window.location = voci[scelto].u; }
      else if (e.key === 'Escape')    { e.stopPropagation(); spegni(); }
    });

    // Il ritardo serve al clic: senza, il blur spegnerebbe l'elenco
    // prima che il collegamento riceva il colpo di mouse.
    campo.addEventListener('blur', function () { setTimeout(spegni, 160); });
  }

  var blocchi = document.querySelectorAll('.ricerca-blocco');
  for (var i = 0; i < blocchi.length; i++) { collega(blocchi[i]); }

  var box = document.querySelector('.cercatore');
  if (!box) { return; }
  var campo = box.querySelector('.ricerca-campo');

  function mostra(aperto) {
    box.hidden = !aperto;
    if (aperto) { campo.focus(); campo.select(); }
  }

  var lenti = document.querySelectorAll('.menu-lente');
  for (var j = 0; j < lenti.length; j++) {
    lenti[j].addEventListener('click', function (e) { e.preventDefault(); mostra(true); });
  }
  box.querySelector('.cercatore-fondo').addEventListener('click', function () { mostra(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !box.hidden) { mostra(false); }
  });
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
