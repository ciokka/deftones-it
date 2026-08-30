<?php $tracce = json_decode((string)$d['tracklist'], true) ?: []; ?>

<article class="disco">
  <div class="disco-testa">
    <?php if ($d['copertina']): ?>
      <img class="disco-grande" src="<?= e($d['copertina']) ?>" alt="Copertina di <?= e($d['titolo']) ?>">
    <?php endif ?>

    <div class="disco-dati">
      <div class="meta"><span class="etichetta"><?= e($d['tipo']) ?></span></div>
      <h1><?= e($d['titolo']) ?></h1>
      <dl class="scheda-dati">
        <?php
        $righe = [
            'Uscita'     => $d['data_uscita'] ? dataIt($d['data_uscita']) : ($d['anno'] ?: null),
            'Etichetta'  => $d['etichetta'],
            'Produttore' => $d['produttore'],
            'Brani'      => $tracce ? count($tracce) : null,
        ];
        foreach ($righe as $k => $v): if (!$v) { continue; } ?>
          <dt><?= e($k) ?></dt><dd><?= e((string)$v) ?></dd>
        <?php endforeach ?>
      </dl>
    </div>
  </div>

  <?php if ($d['descrizione_it']): ?>
    <div class="corpo disco-testo"><?= $d['descrizione_it'] ?></div>
  <?php else: ?>
    <p class="vuoto">La scheda di questo disco non è ancora stata scritta.</p>
  <?php endif ?>

  <?php if ($tracce): ?>
    <h2 class="titolo-gruppo">Tracklist</h2>
    <ol class="tracklist">
      <?php foreach ($tracce as $t): ?>
        <li>
          <span class="traccia-n"><?= (int)$t['n'] ?></span>
          <span class="traccia-titolo"><?= e($t['titolo']) ?></span>
          <?php if (!empty($t['durata'])): ?><span class="traccia-durata"><?= e($t['durata']) ?></span><?php endif ?>
        </li>
      <?php endforeach ?>
    </ol>
  <?php endif ?>
</article>

<?php if ($collegati): ?>
<section class="correlate">
  <h2>dall'archivio</h2>
  <div class="elenco">
    <?php foreach ($collegati as $x): ?>
      <article class="scheda">
        <div class="meta"><time><?= e(quandoIt($x['pubblicato_il'])) ?></time></div>
        <h2><a href="<?= u('notizie/' . $x['slug'] . '/') ?>"><?= e($x['titolo_it']) ?></a></h2>
      </article>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>
