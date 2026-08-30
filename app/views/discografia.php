<h1 class="titolo-sezione">Discografia</h1>
<p class="occhiello">
  Tutti i dischi, in ordine d'uscita. Date, etichette e tracklist vengono
  da MusicBrainz: sono un registro, non un ricordo.
</p>

<?php
// Album prima, poi tutto il resto: gli EP e le raccolte sono di un'altra
// natura e mescolarli in una griglia sola confonde chi cerca i dischi veri.
$gruppi = ['album' => [], 'altro' => []];
foreach ($dischi as $d) {
    $gruppi[$d['tipo'] === 'album' ? 'album' : 'altro'][] = $d;
}
?>

<?php foreach ([['album', ''], ['altro', 'Ep, raccolte e altro']] as [$chiave, $etichetta]): ?>
  <?php if (!$gruppi[$chiave]) { continue; } ?>
  <?php if ($etichetta): ?><h2 class="titolo-gruppo"><?= e($etichetta) ?></h2><?php endif ?>

  <div class="griglia-dischi">
    <?php foreach ($gruppi[$chiave] as $d): ?>
      <a class="disco-carta" href="<?= u('discografia/' . $d['slug'] . '/') ?>">
        <span class="disco-copertina">
          <?php if ($d['copertina']): ?>
            <img src="<?= e($d['copertina']) ?>" alt="" loading="lazy" decoding="async">
          <?php else: ?>
            <span class="disco-senza" aria-hidden="true"><?= e(mb_substr($d['titolo'], 0, 1)) ?></span>
          <?php endif ?>
        </span>
        <span class="disco-titolo"><?= e($d['titolo']) ?></span>
        <span class="disco-anno"><?= $d['anno'] ? e((string)$d['anno']) : e($d['tipo']) ?></span>
      </a>
    <?php endforeach ?>
  </div>
<?php endforeach ?>
