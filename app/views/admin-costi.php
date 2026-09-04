<?php
/**
 * I costi dell'API, che sono l'unica spesa ricorrente del sito.
 *
 * I numeri sono una stima: Anthropic non espone la spesa reale, quindi
 * moltiplichiamo i token che abbiamo contato noi per le tariffe. Lo
 * scarto col conto vero è nell'ordine dei centesimi, ma va detto — un
 * numero che si spaccia per una fattura si finisce per crederlo.
 */
$euro = fn(float $v): string => number_format($v, 2, ',', '.') . ' €';
$mila = fn(int $v): string => number_format($v, 0, ',', '.');

/** Il giorno più caro fa da riferimento per la lunghezza delle barre. */
$piccoMax = 0.0;
foreach ($giorni as $g) {
    $piccoMax = max($piccoMax, costoEuro((int)$g['ti'], (int)$g['tou']));
}
?>
<div class="pannello">
  <p><a class="torna" href="<?= u('admin/') ?>"><?= icona('indietro') ?> torna alle bozze</a></p>

  <div class="pannello-testa">
    <h1>costi <span class="conta"><?= e($euro($totali['questo mese']['euro'])) ?></span></h1>
  </div>

  <p class="occhiello">
    Una stima, non una fattura: Anthropic non espone la spesa reale, e
    questi numeri vengono dai token contati da noi moltiplicati per le
    tariffe di <?= e($modello) ?> — cinque dollari per milione in
    ingresso, venticinque in uscita, convertiti in euro. L'uscita costa
    cinque volte l'ingresso, ed è il motivo per cui scrivere articoli
    costa e leggere feed no.
  </p>

  <div class="costi-riquadri">
    <?php foreach ($totali as $nome => $t): ?>
      <div class="costo-riquadro<?= $nome === 'questo mese' ? ' costo-forte' : '' ?>">
        <span class="costo-nome"><?= e($nome) ?></span>
        <span class="costo-cifra"><?= e($euro($t['euro'])) ?></span>
        <span class="costo-sotto">
          <?= (int)$t['giri'] ?> giri · <?= e($mila($t['in'])) ?> in · <?= e($mila($t['out'])) ?> out
        </span>
      </div>
    <?php endforeach ?>
  </div>

  <h1 class="titoletto">ultimi trenta giorni</h1>
  <?php if (!$giorni): ?>
    <p class="vuoto">Nessun giro registrato.</p>
  <?php else: ?>
    <div class="costi-giorni">
      <?php foreach ($giorni as $g): ?>
        <?php
          $c = costoEuro((int)$g['ti'], (int)$g['tou']);
          // Una barra a zero non si vede e sembra un dato mancante: il
          // minimo di due punti dice "quel giorno c'è, e non è costato".
          $largo = $piccoMax > 0 ? max(2, (int)round($c / $piccoMax * 100)) : 2;
        ?>
        <div class="costo-giorno">
          <span class="costo-data"><?= e(date('d/m', strtotime((string)$g['g']))) ?></span>
          <span class="costo-barra"><i style="width: <?= $largo ?>%"></i></span>
          <span class="costo-valore"><?= e($euro($c)) ?></span>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <h1 class="titoletto">per lavoro, negli ultimi trenta giorni</h1>
  <?php if (!$perJob): ?>
    <p class="vuoto">Nessun giro registrato.</p>
  <?php else: ?>
    <div class="bozza registro">
      <?php foreach ($perJob as $j): ?>
        <?php $c = costoEuro((int)$j['ti'], (int)$j['tou']); ?>
        <div>
          <strong><?= e((string)$j['job']) ?></strong> ·
          <?= e($euro($c)) ?> ·
          <?= (int)$j['giri'] ?> giri ·
          <?= e($mila((int)$j['ti'])) ?> token in, <?= e($mila((int)$j['tou'])) ?> out
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <h1 class="titoletto">i dieci giri più cari, da sempre</h1>
  <?php if (!$cari): ?>
    <p class="vuoto">Nessun giro ha ancora consumato token.</p>
  <?php else: ?>
    <div class="bozza registro">
      <?php foreach ($cari as $r): ?>
        <div>
          <strong><?= e($euro(costoEuro((int)$r['token_in'], (int)$r['token_out']))) ?></strong> ·
          <?= e((string)$r['job']) ?> ·
          <?= e(quandoIt((string)$r['iniziato_il'])) ?> ·
          <?= (int)$r['item_elaborati'] ?> elementi ·
          <?= e((string)$r['esito']) ?>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
