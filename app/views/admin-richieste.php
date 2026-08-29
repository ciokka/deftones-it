<div class="pannello">
  <div class="pannello-testa">
    <h1>richieste</h1>
    <div class="azioni">
      <a class="bottone bottone-tenue" href="<?= u('admin/') ?>">torna alle bozze</a>
    </div>
  </div>

  <?php if ($messaggio): ?>
    <div class="<?= $messaggio[0] === 'ok' ? 'avvisoOk' : 'avvisoKo' ?>"><?= e($messaggio[1]) ?></div>
  <?php endif ?>

  <form method="post" action="<?= u('admin/richieste') ?>" class="bozza">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="che" value="nuova">

    <label class="campo"><span>di cosa deve parlare l'articolo</span>
      <input type="text" name="richiesta" maxlength="500" required
             placeholder="la strumentazione di Stephen Carpenter: chitarre, amplificatori, effetti"></label>

    <label class="campo"><span>indicazioni (facoltative): taglio, lunghezza, cosa evitare</span>
      <textarea name="indicazioni" rows="3"
                placeholder="Concentrati sulle otto corde e su come è cambiata dal 2006. Niente prezzi."></textarea></label>

    <p class="sommario" style="margin:0 0 1rem">
      Il modello cerca le fonti sul web, le legge e scrive citandole. Serve
      qualche minuto e costa circa venti centesimi. L'articolo nasce in bozza.
    </p>
    <button class="bottone" type="submit">metti in coda</button>
  </form>

  <?php if (!$richieste): ?>
    <p class="vuoto">Nessuna richiesta.</p>
  <?php endif ?>

  <?php foreach ($richieste as $r): ?>
    <?php
      $etichette = ['attesa' => 'in coda', 'lavorazione' => 'in lavorazione',
                    'fatto' => 'scritto', 'errore' => 'errore'];
      $fonti = $r['fonti'] ? (json_decode((string)$r['fonti'], true) ?: []) : [];
    ?>
    <article class="bozza">
      <div class="meta">
        <span class="etichetta <?= $r['stato'] === 'fatto' ? 'et-tour' : ($r['stato'] === 'errore' ? 'et-dubbio' : '') ?>">
          <?= e($etichette[$r['stato']] ?? $r['stato']) ?>
        </span>
        <time><?= e(quandoIt($r['creato_il'])) ?></time>
        <?php if ($fonti): ?><span><?= count($fonti) ?> fonti consultate</span><?php endif ?>
        <?php if ((int)$r['token_in'] > 0): ?>
          <span><?= number_format(costoEuro((int)$r['token_in'], (int)$r['token_out']), 2, ',', '.') ?> €</span>
        <?php endif ?>
      </div>

      <h2><?= e($r['richiesta']) ?></h2>
      <?php if ($r['indicazioni']): ?>
        <p class="sommario"><em><?= e($r['indicazioni']) ?></em></p>
      <?php endif ?>

      <?php if ($r['nota']): ?>
        <p class="sommario" style="color:var(--avviso)"><?= e($r['nota']) ?></p>
      <?php endif ?>

      <?php if ($r['titolo_it']): ?>
        <p class="sommario">
          → <a href="<?= u('admin/anteprima/' . (int)$r['articolo_id']) ?>"
               style="color:var(--accento)"><?= e($r['titolo_it']) ?></a>
          <?= $r['stato_articolo'] === 'pubblicato' ? ' (online)' : ' (bozza)' ?>
        </p>
      <?php endif ?>

      <?php if ($fonti): ?>
        <details style="margin-top:.75rem">
          <summary style="font-size:.8125rem;color:var(--testo-tenue);cursor:pointer">
            pagine consultate</summary>
          <ul style="font-size:.8125rem;margin:.5rem 0 0;padding-left:1.2rem;color:var(--testo-tenue)">
            <?php foreach ($fonti as $url => $titolo): ?>
              <li><a href="<?= e((string)$url) ?>" target="_blank" rel="noopener"
                     style="color:var(--testo-tenue)"><?= e((string)$titolo) ?></a></li>
            <?php endforeach ?>
          </ul>
        </details>
      <?php endif ?>

      <div class="azioni">
        <?php foreach ([['rifai', 'rifai', ' bottone-tenue'], ['elimina', 'elimina', ' bottone-tenue']] as [$c, $et, $cl]): ?>
          <?php if ($c === 'rifai' && $r['stato'] === 'attesa') { continue; } ?>
          <form method="post" action="<?= u('admin/richieste') ?>" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="che" value="<?= $c ?>">
            <button class="bottone<?= $cl ?>" type="submit"><?= $et ?></button>
          </form>
        <?php endforeach ?>
      </div>
    </article>
  <?php endforeach ?>
</div>
