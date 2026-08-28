<?php
/** Costruisce un URL del pannello conservando i filtri attivi. */
$link = function (array $cambia = []) use ($cerca, $anno, $cat, $ordine, $pagina): string {
    $p = array_filter([
        'q' => $cerca, 'anno' => $anno ?: '', 'cat' => $cat,
        'ord' => $ordine, 'p' => $pagina > 1 ? $pagina : '',
    ] + [], fn($v) => $v !== '' && $v !== 0);
    $p = array_merge($p, $cambia);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return u('admin/') . ($p ? '?' . http_build_query($p) : '');
};
?>
<div class="pannello">

  <div class="pannello-testa">
    <h1>bozze <span style="color:var(--testo-tenue)"><?= (int)$totale ?></span></h1>
    <div class="azioni">
      <form method="post" action="<?= u('admin/azione') ?>" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="che" value="svuota">
        <button class="bottone bottone-tenue" type="submit">svuota cache</button>
      </form>
      <a class="bottone bottone-tenue" href="<?= u('/') ?>">vedi il sito</a>
      <a class="bottone bottone-tenue" href="<?= u('admin/esci') ?>">esci</a>
    </div>
  </div>

  <?php if ($messaggio): ?>
    <div class="<?= $messaggio[0] === 'ok' ? 'avvisoOk' : 'avvisoKo' ?>"><?= e($messaggio[1]) ?></div>
  <?php endif ?>

  <!-- filtri -->
  <form method="get" action="<?= u('admin/') ?>" class="filtri">
    <input type="search" name="q" value="<?= e($cerca) ?>" placeholder="cerca nel titolo…">
    <select name="anno">
      <option value="">tutti gli anni</option>
      <?php foreach ($anni as $a): ?>
        <option value="<?= (int)$a['a'] ?>" <?= $anno === (int)$a['a'] ? 'selected' : '' ?>>
          <?= (int)$a['a'] ?> (<?= (int)$a['n'] ?>)
        </option>
      <?php endforeach ?>
    </select>
    <select name="cat">
      <option value="">tutte le categorie</option>
      <?php foreach ($categorie as $c): ?>
        <option value="<?= e($c['categoria']) ?>" <?= $cat === $c['categoria'] ? 'selected' : '' ?>>
          <?= e($c['categoria']) ?> (<?= (int)$c['n'] ?>)
        </option>
      <?php endforeach ?>
    </select>
    <select name="ord">
      <?php foreach (['rilevanza' => 'per rilevanza', 'lunghi' => 'i più lunghi',
                      'recenti' => 'i più recenti', 'vecchi' => 'i più vecchi'] as $k => $et): ?>
        <option value="<?= $k ?>" <?= $ordine === $k ? 'selected' : '' ?>><?= $et ?></option>
      <?php endforeach ?>
    </select>
    <button class="bottone bottone-tenue" type="submit">filtra</button>
    <?php if ($cerca || $anno || $cat): ?>
      <a class="bottone bottone-tenue" href="<?= u('admin/') ?>">azzera</a>
    <?php endif ?>
  </form>

  <?php if (!$bozze): ?>
    <p class="vuoto">Nessuna bozza con questi filtri.</p>
  <?php else: ?>

  <form method="post" action="<?= u('admin/azione') ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="che" value="multi">

    <?php foreach ($bozze as $b): ?>
      <article class="bozza">
        <div class="meta">
          <label class="scelta">
            <input type="checkbox" name="ids[]" value="<?= (int)$b['id'] ?>">
          </label>
          <span class="punteggio"><?= (int)$b['rilevanza'] ?></span>
          <span class="etichetta et-<?= e($b['categoria']) ?>"><?= e($b['categoria']) ?></span>
          <?php if ($b['attendibilita'] !== 'confermato'): ?>
            <span class="etichetta et-dubbio"><?= e($b['attendibilita']) ?></span>
          <?php endif ?>
          <?php if ($b['fonte_nome']): ?><span><?= e($b['fonte_nome']) ?></span><?php endif ?>
          <time><?= e(dataIt($b['pubblicato_il'] ?: $b['creato_il'])) ?></time>
          <?php if ((int)$b['lunghezza'] > 0): ?>
            <span><?= number_format((int)$b['lunghezza'] / 1000, 1, ',', '.') ?>k</span>
          <?php endif ?>
        </div>

        <h2><?= e($b['titolo_it']) ?></h2>
        <p class="sommario"><?= e(mb_substr($b['sommario_it'], 0, 320)) ?><?= mb_strlen($b['sommario_it']) > 320 ? '…' : '' ?></p>

        <div class="azioni">
          <a class="bottone bottone-tenue" href="<?= u('admin/anteprima/' . (int)$b['id']) ?>">leggi tutto</a>
          <?php if ($b['fonte_url']): ?>
            <a class="bottone bottone-tenue" href="<?= e($b['fonte_url']) ?>" target="_blank" rel="noopener">fonte</a>
          <?php endif ?>
        </div>
      </article>
    <?php endforeach ?>

    <div class="barra-azioni">
      <label class="scelta"><input type="checkbox" id="tutti"> seleziona tutti in pagina</label>
      <button class="bottone" type="submit" name="come" value="pubblica">pubblica selezionati</button>
      <button class="bottone bottone-tenue" type="submit" name="come" value="scarta">scarta selezionati</button>
    </div>
  </form>

  <?php if ($pagine > 1): ?>
    <nav class="paginazione">
      <?php if ($pagina > 1): ?><a href="<?= $link(['p' => $pagina - 1]) ?>">← indietro</a><?php endif ?>
      <span>pagina <?= $pagina ?> di <?= $pagine ?></span>
      <?php if ($pagina < $pagine): ?><a href="<?= $link(['p' => $pagina + 1]) ?>">avanti →</a><?php endif ?>
    </nav>
  <?php endif ?>

  <?php endif ?>

  <?php if ($pubblicate): ?>
    <h1 class="titoletto">online, le ultime</h1>
    <?php foreach ($pubblicate as $p): ?>
      <article class="bozza compatta">
        <div class="meta"><time><?= e(dataIt($p['pubblicato_il'])) ?></time>
          <?php if ($p['fonte_nome']): ?><span><?= e($p['fonte_nome']) ?></span><?php endif ?></div>
        <div class="riga">
          <a href="<?= u('notizie/' . $p['slug'] . '/') ?>"><?= e($p['titolo_it']) ?></a>
          <form method="post" action="<?= u('admin/azione') ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="che" value="ritira">
            <button class="bottone bottone-tenue piccolo" type="submit">ritira</button>
          </form>
        </div>
      </article>
    <?php endforeach ?>
  <?php endif ?>

  <?php if ($ultimo): ?>
    <h1 class="titoletto">ultimi giri</h1>
    <div class="bozza registro">
      <?php foreach ($ultimo as $l): ?>
        <div><strong><?= e($l['job']) ?></strong> · <?= e(quandoIt($l['finito_il'])) ?>
          · <?= e((string)$l['esito']) ?>
          <?= $l['messaggio'] ? '· ' . e(mb_substr((string)$l['messaggio'], 0, 90)) : '' ?></div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

</div>

<script>
// Unica riga di script del sito: la casella "seleziona tutti".
document.getElementById('tutti')?.addEventListener('change', function () {
  document.querySelectorAll('input[name="ids[]"]').forEach(c => { c.checked = this.checked; });
});
</script>
