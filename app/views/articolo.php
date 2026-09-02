<?php /* Il titolo sta SOPRA la fotografia, non prima né dopo: la
         copertina è il fondo su cui si legge il titolo, come l'apertura
         della home. Prima la foto stava fra il titolo e il testo e
         spezzava in due l'attacco del pezzo.
         Senza fotografia non c'è nessun fondo: la testata resta il
         blocco di testo che era, in cima alla colonna. */ ?>
<?php $foto = copertinaImg($a, true); ?>
<article class="articolo<?= $foto ? ' articolo-foto' : '' ?>">

  <header class="testa<?= $foto ? ' testa-foto' : '' ?>">
    <?php if ($foto): ?>
      <div class="testa-sfondo" aria-hidden="true"><?= $foto ?></div>
      <div class="testa-velo" aria-hidden="true"></div>
    <?php endif ?>

    <div class="testa-interno">
      <div class="meta">
        <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
        <?php if ($a['attendibilita'] !== 'confermato'): ?>
          <span class="etichetta et-dubbio"><?= e($a['attendibilita']) ?></span>
        <?php endif ?>
        <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(dataIt($a['pubblicato_il'])) ?></time>
        <?= etichettaHot($a['rilevanza'] ?? null) ?>
      </div>

      <?php if (!empty($raccolta)): ?>
        <p class="appartiene">
          parte di <a href="<?= u('raccolte/' . $raccolta['slug'] . '/') ?>"><?= e($raccolta['titolo']) ?></a>
        </p>
      <?php endif ?>

      <h1><?= e($a['titolo_it']) ?></h1>

      <?php /* Il credito della foto sta qui e non in fondo alla pagina:
               una foto CC BY è libera *a condizione* che l'autore sia
               citato accanto a quello che si sta guardando. */ ?>
      <?= $foto ? creditoImmagine($a, 'p') : '' ?>
    </div>
  </header>

  <?php /* L'avviso esce dalla testata: sopra la fotografia un riquadro
           d'allarme diventa un cartello, e quello che deve saltare
           all'occhio lì è il titolo. Qui apre il testo, che è il punto
           in cui serve. */ ?>
  <?php if ($a['attendibilita'] !== 'confermato'): ?>
    <p class="nota-dubbio">
      Notizia non confermata da fonti ufficiali. Riportata così come circola.
    </p>
  <?php endif ?>

  <div class="corpo">
    <?php if (!empty($a['corpo_it'])): ?>
      <?php /* Articoli dell'archivio: il sommario è solo un estratto del
               corpo, ripeterlo in cima sarebbe una doppione. */ ?>
      <?= facciateVideo((string)$a['corpo_it']) ?>
    <?php else: ?>
      <p><?= nl2br(e($a['sommario_it'])) ?></p>
    <?php endif ?>
  </div>

  <?php if ($a['fonte_url']): ?>
  <p class="rimando">
    Questo è un riassunto. L'articolo originale è di
    <strong><?= e($a['fonte_nome']) ?></strong>:
    <a href="<?= e($a['fonte_url']) ?>" target="_blank" rel="noopener">leggilo lì <span aria-hidden="true">→</span></a>
  </p>
  <?php else: ?>
  <?php /* Senza fonte esterna non c'è nessun rimando da fare: il pezzo è
           nostro, e al posto di una riga vuota ci va detto. */ ?>
  <p class="rimando rimando-nostro"><?= bollino(true) ?></p>
  <?php endif ?>

  <?php
  $indirizzo = cfg('site_url') . u('notizie/' . $a['slug'] . '/');
  $q = rawurlencode($indirizzo);
  $t = rawurlencode($a['titolo_it']);
  ?>
  <div class="condividi" data-url="<?= e($indirizzo) ?>" data-titolo="<?= e($a['titolo_it']) ?>">
    <span class="condividi-etichetta">condividi</span>
    <?= condividiMini($a['slug'], $a['titolo_it']) ?>

    <?php /* Link semplici, non pulsanti ufficiali: quelli caricherebbero
             codice dai server di Meta e X e traccerebbero chi apre la
             pagina anche senza cliccare. Così non parte nulla finché
             non è il lettore a volerlo. */ ?>
    <a class="condividi-voce" href="https://wa.me/?text=<?= $t ?>%20<?= $q ?>"
       target="_blank" rel="noopener nofollow">whatsapp</a>
    <a class="condividi-voce" href="https://t.me/share/url?url=<?= $q ?>&amp;text=<?= $t ?>"
       target="_blank" rel="noopener nofollow">telegram</a>
    <a class="condividi-voce" href="https://www.facebook.com/sharer/sharer.php?u=<?= $q ?>"
       target="_blank" rel="noopener nofollow">facebook</a>
    <a class="condividi-voce" href="https://twitter.com/intent/tweet?url=<?= $q ?>&amp;text=<?= $t ?>"
       target="_blank" rel="noopener nofollow">x</a>
    <button class="condividi-voce condividi-copia" type="button">copia link</button>
  </div>

  <?php $tag = json_decode((string)$a['tag'], true) ?: []; ?>
  <?php if ($tag): ?>
  <p class="tag">
    <?php foreach ($tag as $tg): ?>
      <a href="<?= u('tag/' . rawurlencode($tg) . '/') ?>">#<?= e($tg) ?></a>
    <?php endforeach ?>
  </p>
  <?php endif ?>
</article>

<?php if ($altri): ?>
<section class="correlate">
  <h2><?= !empty($raccolta) ? 'continua la storia' : 'altre notizie' ?></h2>
  <div class="elenco">
    <?php foreach ($altri as $x): ?>
      <article class="scheda">
        <div class="meta"><time><?= e(quandoIt($x['pubblicato_il'])) ?></time></div>
        <h2><a href="<?= u('notizie/' . $x['slug'] . '/') ?>"><?= e($x['titolo_it']) ?></a></h2>
      </article>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>
