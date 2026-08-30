<div class="pannello">
  <p><a href="<?= u('admin/') ?>" style="color:var(--testo-tenue);font-size:.875rem">← torna alle bozze</a></p>

  <div class="meta">
    <span class="punteggio"><?= (int)$a['rilevanza'] ?></span>
    <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
    <?php if ($a['fonte_nome']): ?><span><?= e($a['fonte_nome']) ?></span><?php endif ?>
    <time><?= e(dataIt($a['pubblicato_il'] ?: $a['creato_il'])) ?></time>
    <?php if (!empty($a['url_vecchio'])): ?>
      <span>vecchio indirizzo: <?= e($a['url_vecchio']) ?></span>
    <?php endif ?>
  </div>

  <article class="articolo" style="padding-top:1rem">
    <h1><?= e($a['titolo_it']) ?></h1>
    <?= copertina($a, true) ?>
    <div class="corpo">
      <?php if (!empty($a['corpo_it'])): ?>
        <?= facciateVideo((string)$a['corpo_it']) ?>
      <?php else: ?>
        <p><?= nl2br(e($a['sommario_it'])) ?></p>
      <?php endif ?>
    </div>
  </article>

  <div class="barra-azioni">
    <?php /* "con copertina" per prima: è quella che si usa quasi sempre.
             Ci mette qualche secondo perché scarica davvero la foto. */ ?>
    <?php foreach ([
        ['pubblica-copertina', 'pubblica con copertina', ''],
        ['pubblica',           'pubblica',               ' bottone-tenue'],
        ['scarta',             'scarta',                 ' bottone-tenue'],
    ] as [$che, $et, $cl]): ?>
      <form method="post" action="<?= u('admin/azione') ?>" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <input type="hidden" name="che" value="<?= $che ?>">
        <button class="bottone<?= $cl ?>" type="submit"><?= $et ?></button>
      </form>
    <?php endforeach ?>
    <?php if (!empty($a['immagine_origine'])): ?>
      <form method="post" action="<?= u('admin/azione') ?>" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <input type="hidden" name="che" value="copertina-no">
        <button class="bottone bottone-tenue" type="submit">altra copertina</button>
      </form>
    <?php endif ?>
    <?php if ($a['fonte_url']): ?>
      <a class="bottone bottone-tenue" href="<?= e($a['fonte_url']) ?>" target="_blank" rel="noopener">fonte</a>
    <?php endif ?>
  </div>
</div>
