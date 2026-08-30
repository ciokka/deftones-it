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
<link rel="stylesheet" href="<?= u('assets/stile.css') ?>?v=58">
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
    u('notizie')            => 'Notizie',
    u('raccolte/')          => 'Raccolte',
    u('discografia/')       => 'Dischi',
    u('categoria/tour/')    => 'Tour',
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
    <div class="colofone">
      <span><?= date('Y') ?></span>
      <a href="<?= u('privacy') ?>">Privacy</a>

      <?php /* rel="me" dice che questi profili sono dello stesso autore
               del sito; noopener perché aprono in una scheda nuova. */ ?>
      <nav class="social" aria-label="Altrove">
        <a href="https://www.facebook.com/deftones.it" rel="me noopener"
           target="_blank" aria-label="deftones.it su Facebook" title="Facebook">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
            <path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.09 24 18.1 24 12.07Z"/>
          </svg>
        </a>
        <a href="https://www.instagram.com/deftones.it" rel="me noopener"
           target="_blank" aria-label="deftones.it su Instagram" title="Instagram">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
               stroke-width="1.9" aria-hidden="true">
            <rect x="2.6" y="2.6" width="18.8" height="18.8" rx="5.4"/>
            <circle cx="12" cy="12" r="4.6"/>
            <circle cx="17.6" cy="6.4" r="1.25" fill="currentColor" stroke="none"/>
          </svg>
        </a>
        <a href="<?= u('feed.xml') ?>" aria-label="Feed RSS" title="Feed RSS">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
            <circle cx="5.6" cy="18.4" r="2.3"/>
            <path d="M3.3 3.6v3.1c7.6 0 13.8 6.2 13.8 13.8h3.1c0-9.3-7.6-16.9-16.9-16.9Zm0 6.2v3.1c4.2 0 7.6 3.4 7.6 7.6h3.1c0-5.9-4.8-10.7-10.7-10.7Z"/>
          </svg>
        </a>
      </nav>
    </div>
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

  // Due modi di condividere, e se ne mostra uno solo.
  //
  // Dove c'è il pannello di sistema resta l'iconcina, la stessa delle
  // schede: conosce le app installate ed è più comoda di qualunque
  // elenco. Prima al suo posto veniva creato un pulsante con scritto
  // "condividi", che finiva accanto all'etichetta "condividi" — la
  // stessa parola due volte di fila.
  //
  // Dove il pannello non c'è — Firefox su desktop, per esempio — restano
  // i collegamenti ai singoli servizi, che nessun sistema offrirebbe, e
  // l'iconcina se ne va perché saprebbe fare solo copia-indirizzo.
  var mini = box.querySelector('.condividi-mini');
  if (navigator.share) {
    var voci = box.querySelectorAll('.condividi-voce');
    for (var i = 0; i < voci.length; i++) { voci[i].remove(); }
    return;
  }
  if (mini) { mini.remove(); }

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

// "Carica altro". Il collegamento esiste già e porta alla pagina
// successiva: senza JavaScript funziona così, ed è anche il modo in cui
// un motore di ricerca raggiunge l'archivio. Qui lo intercettiamo,
// chiediamo alla stessa pagina solo le schede e le aggiungiamo in fondo.
(function () {
  var elenco = document.getElementById('elenco-notizie');
  if (!elenco || !window.fetch) { return; }

  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('.bottone-altro') : null;
    if (!b || b.dataset.occupato) { return; }
    e.preventDefault();

    var url = b.getAttribute('href');
    var quante = parseInt(b.dataset.perPagina || '24', 10);
    var prima = b.textContent;
    b.dataset.occupato = '1';
    b.textContent = 'carico…';

    fetch(url + '&frammento=1')
      .then(function (r) { return r.ok ? r.text() : null; })
      .then(function (html) {
        if (html === null) { throw new Error('risposta non valida'); }
        var tmp = document.createElement('div');
        tmp.innerHTML = html;                 // markup nostro, già scappato da PHP

        // Si prendono solo le schede, non tutto quello che è arrivato.
        // La prima versione appendeva qualunque cosa, e quando il
        // frammento è tornato come pagina intera si sono trovati logo e
        // menu in mezzo all'elenco. Filtrando, un frammento sbagliato non
        // aggiunge niente invece di aggiungere il sito.
        var nuove = tmp.querySelectorAll('article.scheda');
        var n = nuove.length;
        for (var i = 0; i < n; i++) { elenco.appendChild(nuove[i]); }

        // Meno schede del previsto vuol dire che era l'ultima pagina.
        if (n < quante) { b.parentNode.removeChild(b); return; }
        b.setAttribute('href', url.replace(/([?&]p=)\d+/, function (_, p) {
          return p + (parseInt(url.match(/[?&]p=(\d+)/)[1], 10) + 1);
        }));
        b.textContent = prima;
        delete b.dataset.occupato;
      })
      .catch(function () {
        // Se non riesce, il pulsante torna a essere un collegamento
        // normale: al clic dopo si cambia pagina, come senza JavaScript.
        b.textContent = prima;
        delete b.dataset.occupato;
      });
  });
})();

// Il carosello dell'apertura.
//
// Le diapositive sono sovrapposte e si muovono in verticale: quella che
// esce sale, quella che entra arriva da sotto. Non c'è nessun
// contenitore che scorre, quindi la rotella del mouse e il dito sul
// telefono restano della pagina: il carosello non se li prende.
(function () {
  var box = document.querySelector('[data-carosello]');
  if (!box) { return; }
  var pista = box.querySelector('.carosello-piste');
  var diapo = pista.children;
  var punti = box.querySelectorAll('.carosello-punto');
  var quante = diapo.length;
  if (quante < 2) { return; }

  var lento = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var attivo = 0, fermo = false, attesa = null, inMoto = false;
  // Di suo un comando cambia diapositiva; con l'aggancio attivo sposta
  // invece la pagina, ed è lo scorrimento a cambiarla di conseguenza.
  var portaA = null, agganciato = false;

  // L'asse dell'animazione segue il gesto: chi scorre col dito vede le
  // diapositive muoversi in orizzontale, nella direzione in cui ha
  // trascinato; l'avanzamento automatico e le frecce le fanno salire.
  // conMoto: la diapositiva che entra va prima messa dalla parte giusta
  // SENZA transizione, altrimenti scivolerebbe da dove si trovava prima.
  function poni(el, asse, quanto, opacita, conMoto) {
    el.style.transition = conMoto && !lento
      ? 'transform .75s cubic-bezier(.22,.61,.36,1), opacity .5s ease'
      : 'none';
    el.style.transform = quanto ? 'translate' + asse + '(' + quanto + '%)' : 'none';
    el.style.opacity = opacita;
    el.style.pointerEvents = opacita ? 'auto' : 'none';
  }

  function vaiA(n, asse) {
    asse = asse || 'Y';
    n = ((n % quante) + quante) % quante;
    if (n === attivo || inMoto) { return; }

    // Avanti l'entrante arriva da sotto (o da destra), indietro dall'alto
    // (o da sinistra). Il giro completo 2→0 conta come "avanti".
    var avanti = n > attivo;
    if (n === 0 && attivo === quante - 1) { avanti = true; }
    else if (n === quante - 1 && attivo === 0) { avanti = false; }

    var uscente = diapo[attivo], entrante = diapo[n];
    poni(entrante, asse, avanti ? 100 : -100, 0, false);
    void entrante.offsetHeight;                 // costringe il browser a prenderne atto
    poni(entrante, asse, 0, 1, true);
    poni(uscente, asse, avanti ? -100 : 100, 0, true);

    inMoto = true;
    setTimeout(function () {
      inMoto = false;
      // Scorrendo in fretta si può essere già saltati oltre: appena la
      // transizione finisce si recupera il ritardo.
      if (agganciato && desiderato !== attivo) { applica(); }
    }, lento ? 0 : 780);

    attivo = n;
    for (var i = 0; i < punti.length; i++) {
      punti[i].classList.toggle('attivo', i === attivo);
    }
  }

  for (var i = 0; i < punti.length; i++) {
    (function (n) {
      punti[n].addEventListener('click', function () {
        basta();
        if (portaA) { portaA(n); } else { vaiA(n); }
      });
    })(i);
  }
  var frecce = box.querySelectorAll('.carosello-freccia');
  for (var j = 0; j < frecce.length; j++) {
    (function (f) {
      f.addEventListener('click', function () {
        basta();
        var n = attivo + parseInt(f.getAttribute('data-vai'), 10);
        if (portaA) { portaA(Math.min(quante - 1, Math.max(0, n))); } else { vaiA(n); }
      });
    })(frecce[j]);
  }

  // Lo scorrimento col dito. Un gesto orizzontale non ha altri
  // significati sul telefono — quello verticale è della pagina, e non lo
  // tocchiamo — quindi si può leggere senza rubare niente a nessuno.
  var pX = 0, pY = 0, valido = false;
  pista.addEventListener('touchstart', function (e) {
    if (e.touches.length !== 1) { valido = false; return; }
    pX = e.touches[0].clientX; pY = e.touches[0].clientY; valido = true;
  }, { passive: true });

  pista.addEventListener('touchend', function (e) {
    if (!valido) { return; }
    valido = false;
    var t = e.changedTouches[0];
    var dx = t.clientX - pX, dy = t.clientY - pY;
    // Solo se il gesto è chiaramente orizzontale e abbastanza lungo: chi
    // sta scorrendo la pagina in diagonale non deve cambiare notizia.
    if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy) * 1.5) { return; }
    basta();
    vaiA(attivo + (dx < 0 ? 1 : -1), 'X');
  }, { passive: true });

  // --- l'aggancio allo scorrimento -------------------------------
  //
  // Da schermo largo il carosello resta appiccicato in cima per un tratto
  // di pagina, e la diapositiva segue la corsa percorsa. Non si
  // intercetta niente: la rotella muove la pagina come sempre, e questo
  // codice si limita a guardare a che punto è arrivata.
  //
  // Che l'aggancio sia attivo lo dice il CSS, non una soglia scritta qui:
  // se l'involucro è più alto del carosello, c'è una corsa da percorrere.
  // Una sola verità sul dove comincia il desktop.
  var aggancio = box.parentNode;
  var desiderato = 0;

  function corsa() {
    return aggancio ? aggancio.offsetHeight - box.offsetHeight : 0;
  }

  /**
   * Da quale punto della pagina si comincia a contare.
   *
   * Il carosello sta sotto la testata, quindi prima di agganciarsi deve
   * salire dell'altezza della testata stessa. Contando dalla posizione
   * dell'involucro, quel tratto risultava a zero: si scorreva, l'immagine
   * saliva, e non cambiava niente — un ritardo di ottanta pixel che si
   * sente tutto perché è il primo gesto.
   */
  function inizio() {
    var testata = document.querySelector('.testata');
    var alto = aggancio.getBoundingClientRect().top + window.pageYOffset;
    return Math.max(0, alto - (testata ? testata.offsetHeight : 0));
  }

  function applica() {
    if (!inMoto && desiderato !== attivo) { vaiA(desiderato, 'Y'); }
  }

  var inAttesa = false;
  function guarda() {
    var totale = corsa();
    if (totale < 50) { return; }              // niente aggancio: mobile
    var fatto = Math.min(Math.max(window.pageYOffset - inizio(), 0), totale);
    var n = Math.min(quante - 1, Math.floor(fatto / totale * quante));
    if (n !== desiderato) { desiderato = n; applica(); }
  }

  if (corsa() >= 50) {
    agganciato = true;
    fermo = true;                             // qui comanda lo scorrimento
    window.addEventListener('scroll', function () {
      if (inAttesa) { return; }
      inAttesa = true;
      requestAnimationFrame(function () { inAttesa = false; guarda(); });
    }, { passive: true });
    window.addEventListener('resize', guarda, { passive: true });
    guarda();

    // Con l'aggancio attivo, indicatori e frecce non cambiano diapositiva
    // di nascosto: portano la pagina al punto in cui quella diapositiva
    // sta, altrimenti il primo movimento di rotella le rimetterebbe dove
    // dice lo scorrimento.
    portaA = function (n) {
      var passo = corsa() / quante;
      window.scrollTo({ top: inizio() + passo * n + passo / 2, behavior: 'smooth' });
    };
  }

  // L'avanzamento automatico si ferma per sempre appena tocchi qualcosa:
  // una pagina che continua a muoversi mentre stai leggendo è la ragione
  // per cui i caroselli hanno la fama che hanno.
  function basta() { fermo = true; clearInterval(attesa); }
  box.addEventListener('pointerdown', basta, { passive: true, once: true });
  box.addEventListener('focusin', basta);

  // Niente movimento se il sistema chiede di non muovere niente, e niente
  // avanzamento mentre la scheda è in secondo piano: tornando alla pagina
  // troveresti la terza notizia al posto di quella che stavi guardando.
  // Con l'aggancio attivo l'avanzamento a tempo non serve: a decidere
  // quale notizia si vede è dove sei arrivato a scorrere.
  if (lento || agganciato) { return; }
  attesa = setInterval(function () {
    if (fermo || document.hidden) { return; }
    vaiA(attivo + 1);
  }, 7000);
})();

// Il clic sulla facciata di un video la sostituisce col video vero.
// Fino a quel momento nessuna richiesta è partita verso Google.
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('.video-avvia') : null;
    if (!b) { return; }
    var box = b.parentNode;
    var id = box.getAttribute('data-video');
    if (!id) { return; }

    var f = document.createElement('iframe');
    f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id)
          + '?autoplay=1&rel=0';
    f.title = 'Video YouTube';
    f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    f.setAttribute('allowfullscreen', '');
    f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    box.textContent = '';
    box.appendChild(f);
  });
})();

// Un'immagine che non carica lascerebbe l'icona rotta del browser.
// Le immagini degli articoli dal 2002 al 2011 sono perse: i file non
// esistono più, e mostrare un riquadro grigio al loro posto riempiva i
// pezzi vecchi di rettangoli che non dicono niente. Le nascondiamo.
//
// Il tag <img> resta però nel documento, con il suo indirizzo: se un
// giorno salta fuori un backup basta copiare i file sotto
// /media/legacy/ e le immagini tornano da sole, senza reimportare nulla.
//
// L'ascoltatore è uno solo ed è in fase di cattura, perché "error" sulle
// immagini non risale l'albero.
(function () {
  document.addEventListener('error', function (e) {
    var t = e.target;
    if (t && t.tagName === 'IMG' && !t.dataset.mancante) {
      t.dataset.mancante = '1';
      t.classList.add('img-mancante');
    }
  }, true);
})();
</script>

</body>
</html>
