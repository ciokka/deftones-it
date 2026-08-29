<article class="articolo">
  <div class="meta">
    <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
    <?php if ($a['attendibilita'] !== 'confermato'): ?>
      <span class="etichetta et-dubbio"><?= e($a['attendibilita']) ?></span>
    <?php endif ?>
    <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(dataIt($a['pubblicato_il'])) ?></time>
  </div>

  <?php if (!empty($raccolta)): ?>
    <p class="appartiene">
      parte di <a href="<?= u('raccolte/' . $raccolta['slug'] . '/') ?>"><?= e($raccolta['titolo']) ?></a>
    </p>
  <?php endif ?>

  <h1><?= e($a['titolo_it']) ?></h1>

  <?php if ($a['attendibilita'] !== 'confermato'): ?>
    <p class="nota-dubbio">
      Notizia non confermata da fonti ufficiali. Riportata così come circola.
    </p>
  <?php endif ?>

  <div class="corpo">
    <?php if (!empty($a['corpo_it'])): ?>
      <?php /* Articoli dell'archivio: il sommario è solo un estratto del
               corpo, ripeterlo in cima sarebbe una doppione. */ ?>
      <?= $a['corpo_it'] ?>
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
