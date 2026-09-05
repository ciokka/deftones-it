<?php
/**
 * Le schede di un elenco, nude: nessun contenitore, nessun titolo.
 *
 * Sta in un file suo perché la rendono in quattro posti — la home,
 * l'archivio delle notizie, le liste di categoria e tag, e il pezzo che
 * arriva col "carica altro". Erano quattro copie dello stesso markup, e
 * si è visto subito cosa comporta: aggiungendo il bollo "hot" alla
 * copia condivisa, nella home non compariva perché lì ce n'era un'altra.
 *
 * $taglio, se impostato prima del require, cambia dove si tronca il
 * sommario: nella home le schede stanno in colonne più strette.
 */
$taglio = $taglio ?? 170;
?>
<?php foreach ($articoli as $a): ?>
  <article class="scheda">
    <div class="meta">
      <span class="etichetta et-<?= e($a['categoria']) ?>"><?= e($a['categoria']) ?></span>
      <time datetime="<?= e($a['pubblicato_il']) ?>"><?= e(quandoIt($a['pubblicato_il'])) ?></time>
      <?= etichettaHot($a['rilevanza'] ?? null) ?>
    </div>
    <h2><a href="<?= u('notizie/' . $a['slug'] . '/') ?>"><?= e($a['titolo_it']) ?></a></h2>
    <p class="sommario"><?= e(mb_substr($a['sommario_it'], 0, $taglio)) ?>…</p>
    <div class="piede">
      <p class="fonte"><?= $a['fonte_nome'] ? e($a['fonte_nome']) : bollino() ?></p>
      <?= piaceMini((int)$a['id'], (int)($a['piaciuto'] ?? 0)) ?>
      <?= condividiMini($a['slug'], $a['titolo_it']) ?>
    </div>
  </article>
<?php endforeach ?>
