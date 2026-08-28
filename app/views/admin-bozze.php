<div class="pannello">

  <div class="pannello-testa">
    <h1>Bozze <span style="color:var(--testo-tenue);font-weight:400">(<?= count($bozze) ?>)</span></h1>
    <div class="azioni">
      <form method="post" action="<?= u('admin/azione') ?>" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="che" value="svuota">
        <button class="bottone bottone-tenue" type="submit">Svuota cache</button>
      </form>
      <a class="bottone bottone-tenue" href="<?= u('/') ?>">Vedi il sito</a>
      <a class="bottone bottone-tenue" href="<?= u('admin/esci') ?>">Esci</a>
    </div>
  </div>

  <?php if ($messaggio): ?>
    <div class="<?= $messaggio[0] === 'ok' ? 'avvisoOk' : 'avvisoKo' ?>"><?= e($messaggio[1]) ?></div>
  <?php endif ?>

  <?php if (!$bozze): ?>
    <p class="vuoto">Nessuna bozza in attesa. Il prossimo giro è fra qualche ora.</p>
  <?php endif ?>

  <?php foreach ($bozze as $b): ?>
    <article class="bozza">
      <div class="meta">
        <span class="punteggio"><?= (int)$b['rilevanza'] ?></span>
        <span class="etichetta et-<?= e($b['categoria']) ?>"><?= e($b['categoria']) ?></span>
        <?php if ($b['attendibilita'] !== 'confermato'): ?>
          <span class="etichetta et-dubbio"><?= e($b['attendibilita']) ?></span>
        <?php endif ?>
        <span><?= e($b['fonte_nome']) ?></span>
        <time><?= e(quandoIt($b['creato_il'])) ?></time>
      </div>

      <h2><?= e($b['titolo_it']) ?></h2>
      <p class="sommario"><?= e($b['sommario_it']) ?></p>

      <?php if ($b['fonte_url']): ?>
        <p style="font-size:.8125rem;margin:.5rem 0 0">
          <a href="<?= e($b['fonte_url']) ?>" target="_blank" rel="noopener"
             style="color:var(--testo-tenue)">Apri la fonte →</a>
        </p>
      <?php endif ?>

      <div class="azioni">
        <form method="post" action="<?= u('admin/azione') ?>" style="display:inline">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="che" value="pubblica">
          <button class="bottone" type="submit">Pubblica</button>
        </form>
        <form method="post" action="<?= u('admin/azione') ?>" style="display:inline">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="che" value="scarta">
          <button class="bottone bottone-tenue" type="submit">Scarta</button>
        </form>
      </div>
    </article>
  <?php endforeach ?>

  <?php if ($pubblicate): ?>
    <h1 style="font-size:1.25rem;margin:3rem 0 1rem">Online</h1>
    <?php foreach ($pubblicate as $p): ?>
      <article class="bozza" style="padding:.875rem 1.25rem">
        <div class="meta" style="margin:0">
          <time><?= e(quandoIt($p['pubblicato_il'])) ?></time>
          <span><?= e($p['fonte_nome']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
          <a href="<?= u('notizie/' . $p['slug'] . '/') ?>"
             style="font-weight:600;text-decoration:none"><?= e($p['titolo_it']) ?></a>
          <form method="post" action="<?= u('admin/azione') ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="che" value="ritira">
            <button class="bottone bottone-tenue" type="submit"
                    style="padding:.3rem .7rem;font-size:.8125rem">Ritira</button>
          </form>
        </div>
      </article>
    <?php endforeach ?>
  <?php endif ?>

  <?php if ($ultimo): ?>
    <h1 style="font-size:1.25rem;margin:3rem 0 1rem">Ultimi giri</h1>
    <div class="bozza" style="font-size:.875rem;color:var(--testo-tenue)">
      <?php foreach ($ultimo as $l): ?>
        <div style="padding:.35rem 0;border-bottom:1px solid var(--bordo)">
          <strong style="color:var(--testo)"><?= e($l['job']) ?></strong>
          · <?= e(quandoIt($l['finito_il'])) ?>
          · <?= e((string)$l['esito']) ?>
          <?= $l['messaggio'] ? '· ' . e(mb_substr((string)$l['messaggio'], 0, 90)) : '' ?>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

</div>
