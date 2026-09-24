<?php
/**
 * Quale modello scrive gli articoli. L'elenco viene dall'API, quindi un
 * modello appena uscito compare qui da solo, senza toccare il codice;
 * le tariffe invece stanno in lib/modello.php, perché l'API non le dà.
 */
$dollari = fn(float $v): string => '$' . rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
$prezzo = function (string $id) use ($dollari): string {
    [$in, $out, $nota] = tariffe($id);
    return $nota ? $dollari($in) . ' / ' . $dollari($out) . ' per milione' : 'tariffa non nota';
};
?>
<div class="pannello">
  <div class="pannello-testa">
    <h1>modello <span class="conta"><?= e($inUso) ?></span></h1>
  </div>

  <?= avviso($messaggio) ?>

  <p class="occhiello">
    Il modello che scrive gli articoli. <strong>Automatico</strong>
    vuol dire l'Opus più recente: una volta al giorno il sito chiede
    all'API quali modelli esistono e, se ne è uscito uno nuovo, ci passa
    da solo e lo dice nel riepilogo per posta. Se un modello rifiuta una
    richiesta, quella richiesta si rifà con <?= e($riserva) ?>, il modello
    di riserva scritto in config.php.
  </p>

  <?php if (is_array($cambiato) && !empty($cambiato['a'])): ?>
    <p class="sommario">
      Ultimo cambio automatico: da <?= e((string)$cambiato['da']) ?> a
      <strong><?= e((string)$cambiato['a']) ?></strong>, <?= e(quandoIt((string)$cambiato['il'])) ?>.
    </p>
  <?php endif ?>

  <?php if (!$elenco): ?>
    <p class="vuoto">L'elenco dei modelli non è arrivato: l'API non risponde, o in
      config.php manca la chiave. Si riprova alla prossima apertura.</p>
  <?php else: ?>
    <form method="post" action="<?= u('admin/modello') ?>" class="bozza registro scelta-modello">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">

      <label>
        <input type="radio" name="modello" value="auto" <?= $scelta === 'auto' ? 'checked' : '' ?>>
        <strong>automatico</strong> · l'Opus più recente
        <?php if ($scelta === 'auto'): ?>— adesso <?= e($inUso) ?><?php endif ?>
      </label>

      <?php foreach ($elenco as $m): ?>
        <label>
          <input type="radio" name="modello" value="<?= e($m['id']) ?>"
                 <?= $scelta === $m['id'] || ($scelta === '' && $inUso === $m['id']) ? 'checked' : '' ?>>
          <strong><?= e($m['nome']) ?></strong> ·
          <code><?= e($m['id']) ?></code> ·
          <?= e($prezzo($m['id'])) ?>
          <?php if ($m['uscito']): ?> · <?= e(dataIt($m['uscito'])) ?><?php endif ?>
        </label>
      <?php endforeach ?>

      <p><button class="bottone" type="submit">salva</button></p>
    </form>
  <?php endif ?>
</div>
