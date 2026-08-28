<div class="pannello">
  <div class="pannello-testa">
    <h1>raccolte</h1>
    <div class="azioni">
      <a class="bottone bottone-tenue" href="<?= u('admin/') ?>">torna alle bozze</a>
      <a class="bottone bottone-tenue" href="<?= u('raccolte/') ?>">vedi il sito</a>
    </div>
  </div>

  <?php if ($messaggio): ?>
    <div class="<?= $messaggio[0] === 'ok' ? 'avvisoOk' : 'avvisoKo' ?>"><?= e($messaggio[1]) ?></div>
  <?php endif ?>

  <?php foreach ($raccolte as $r): ?>
    <article class="bozza">
      <div class="meta">
        <span class="punteggio"><?= (int)$r['online'] ?>/<?= (int)$r['totali'] ?></span>
        <span class="etichetta <?= $r['stato'] === 'pubblicato' ? 'et-tour' : '' ?>">
          <?= $r['stato'] === 'pubblicato' ? 'online' : 'bozza' ?>
        </span>
        <span><?= e($r['slug']) ?></span>
      </div>

      <h2><?= e($r['titolo']) ?></h2>
      <p class="sommario"><strong><?= e($r['sottotitolo']) ?></strong></p>
      <p class="sommario"><?= e(mb_substr((string)$r['introduzione'], 0, 400)) ?></p>

      <?php if ((int)$r['online'] === 0): ?>
        <p class="sommario" style="color:var(--avviso)">
          Nessun articolo di questa raccolta è pubblicato: online risulterebbe vuota.
        </p>
      <?php endif ?>

      <div class="azioni">
        <form method="post" action="<?= u('admin/raccolte') ?>">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="che" value="<?= $r['stato'] === 'pubblicato' ? 'ritira' : 'pubblica' ?>">
          <button class="bottone<?= $r['stato'] === 'pubblicato' ? ' bottone-tenue' : '' ?>" type="submit">
            <?= $r['stato'] === 'pubblicato' ? 'ritira' : 'pubblica' ?>
          </button>
        </form>
        <a class="bottone bottone-tenue"
           href="<?= u('admin/?cat=evergreen&ord=rilevanza') ?>">bozze</a>
      </div>
    </article>
  <?php endforeach ?>
</div>
