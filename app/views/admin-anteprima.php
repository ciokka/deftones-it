<div class="pannello">
  <p><a class="torna" href="<?= u('admin/') ?>"><?= icona('indietro') ?> torna alle bozze</a></p>

  <div class="meta">
    <span class="punteggio"><?= (int)$a['rilevanza'] ?></span>
    <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
    <?php if ($a['fonte_nome']): ?><span><?= e($a['fonte_nome']) ?></span><?php endif ?>
    <time><?= e(dataIt($a['pubblicato_il'] ?: $a['creato_il'])) ?></time>
    <?php if (!empty($a['url_vecchio'])): ?>
      <span>vecchio indirizzo: <?= e($a['url_vecchio']) ?></span>
    <?php endif ?>
  </div>

  <?php /* La stessa testata dell'articolo pubblicato, titolo sopra la
           fotografia: è qui che si decide se quella foto regge sotto
           quel titolo, e un'anteprima che li mostra separati non fa
           vedere proprio la cosa che si sta giudicando. */ ?>
  <?php $foto = copertinaImg($a, true); ?>
  <article class="articolo<?= $foto ? ' articolo-foto' : '' ?>" style="padding-top:1rem">
    <header class="testa<?= $foto ? ' testa-foto' : '' ?>">
      <?php if ($foto): ?>
        <div class="testa-sfondo" aria-hidden="true"><?= $foto ?></div>
        <div class="testa-velo" aria-hidden="true"></div>
      <?php endif ?>
      <div class="testa-interno">
        <h1><?= e($a['titolo_it']) ?></h1>
        <?= $foto ? creditoImmagine($a, 'p') : '' ?>
      </div>
    </header>
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
        <button class="bottone<?= $cl ?>" type="submit"><?= icona($che === 'scarta' ? 'scarta' : 'pubblica') ?><?= $et ?></button>
      </form>
    <?php endforeach ?>
    <a class="bottone bottone-tenue" href="<?= u('admin/modifica/' . (int)$a['id']) ?>"><?= icona('modifica') ?>modifica</a>
    <a class="bottone bottone-tenue" href="<?= u('admin/copertina/' . (int)$a['id']) ?>"><?= icona('immagine') ?>copertina</a>
    <?php if (!empty($a['immagine_origine'])): ?>
      <form method="post" action="<?= u('admin/azione') ?>" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <input type="hidden" name="che" value="copertina-no">
        <button class="bottone bottone-tenue" type="submit"><?= icona('cambia') ?>altra copertina</button>
      </form>
    <?php endif ?>
    <?php if ($a['fonte_url']): ?>
      <a class="bottone bottone-tenue" href="<?= e($a['fonte_url']) ?>" target="_blank" rel="noopener"><?= icona('fuori') ?>fonte</a>
    <?php endif ?>
  </div>
</div>
