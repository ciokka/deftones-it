<?php if (!$apertura && !$articoli): ?>
  <div class="vuoto">
    <h1>Ancora niente</h1>
    <p>Le prime notizie compariranno appena verranno approvate.</p>
  </div>
<?php else: ?>

  <?php /* Le prime tre notizie diventano le diapositive dell'apertura.
           La pista scorre con scroll-snap, che è del browser: col dito
           funziona anche senza JavaScript, e lo script aggiunge soltanto
           gli indicatori, le frecce e l'avanzamento automatico. */ ?>
  <?php $primi = $apertura; ?>

  <?php /* L'involucro non si vede: serve solo a dare al carosello una
           corsa di scorrimento da percorrere mentre resta agganciato in
           cima. Su mobile è alto quanto il carosello e non fa niente. */ ?>
  <div class="carosello-aggancio">
  <div class="carosello"<?= count($primi) > 1 ? ' data-carosello' : '' ?>>
    <div class="carosello-piste">
      <?php foreach ($primi as $n => $primo): ?>
        <?php $conFoto = copertinaImg($primo, $n === 0); ?>
        <article class="apertura<?= $conFoto ? ' hero' : '' ?>"
                 aria-label="Apertura <?= $n + 1 ?> di <?= count($primi) ?>">
          <?php if ($conFoto): ?>
            <div class="hero-foto"><?= $conFoto ?></div>
            <div class="hero-velo" aria-hidden="true"></div>
          <?php endif ?>

          <div class="hero-interno">
            <div class="hero-testo">
              <div class="meta">
                <span class="etichetta et-<?= e($primo['categoria']) ?>"><?= e($primo['categoria']) ?></span>
                <?php if ($primo['attendibilita'] !== 'confermato'): ?>
                  <span class="etichetta et-dubbio"><?= e($primo['attendibilita']) ?></span>
                <?php endif ?>
                <time datetime="<?= e($primo['pubblicato_il']) ?>"><?= e(quandoIt($primo['pubblicato_il'])) ?></time>
                <?= etichettaHot($primo['rilevanza'] ?? null) ?>
              </div>
              <h1><a href="<?= u('notizie/' . $primo['slug'] . '/') ?>"><?= e($primo['titolo_it']) ?></a></h1>
              <p class="sommario"><?= e(mb_substr($primo['sommario_it'], 0, 260)) ?><?= mb_strlen($primo['sommario_it']) > 260 ? '…' : '' ?></p>
            </div>

            <div class="piede">
              <?php if ($primo['fonte_nome']): ?>
                <p class="fonte">Fonte: <?= e($primo['fonte_nome']) ?></p>
              <?php else: ?>
                <p class="fonte"><?= bollino() ?></p>
              <?php endif ?>
              <div class="piede-destra">
                <?= creditoImmagine($primo, 'p') ?>
                <?= condividiMini($primo['slug'], $primo['titolo_it']) ?>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach ?>
    </div>

    <?php if (count($primi) > 1): ?>
      <div class="carosello-guida">
        <button class="carosello-freccia" type="button" data-vai="-1" aria-label="Notizia precedente">
          <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2.5 10 8 4.5l5.5 5.5"/></svg>
        </button>
        <div class="carosello-punti">
          <?php foreach ($primi as $n => $x): ?>
            <button class="carosello-punto<?= $n === 0 ? ' attivo' : '' ?>" type="button"
                    data-punto="<?= $n ?>" aria-label="Vai alla notizia <?= $n + 1 ?>"><span></span></button>
          <?php endforeach ?>
        </div>
        <button class="carosello-freccia" type="button" data-vai="1" aria-label="Notizia successiva">
          <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2.5 6 8 11.5 13.5 6"/></svg>
        </button>
      </div>
    <?php endif ?>
  </div>
  </div>

  <?php if ($articoli): ?>
  <div class="elenco">
    <?php $taglio = 150; require __DIR__ . '/schede.php'; ?>
  </div>
  <?php endif ?>

<?php endif ?>
