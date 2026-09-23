<?php
/**
 * Quanto si legge il sito.
 *
 * Pagine lette, non persone: il sito non sa chi legge e non se lo
 * chiede, quindi chi torna tre volte conta tre. I programmi che si
 * dichiarano tali sono esclusi; quelli che si travestono da browser no,
 * e gonfiano un po' i numeri. Vanno letti come un andamento.
 */
$mila = fn(int $v): string => number_format($v, 0, ',', '.');
$picco = $giorni ? max($giorni) : 0;
?>
<div class="pannello">

  <div class="pannello-testa">
    <h1>visite <span class="conta"><?= $manca ? '' : e($mila($totali['ultimi 30 gg']['v'])) ?></span></h1>
  </div>

  <p class="occhiello">
    Pagine lette, non persone. Il sito non registra chi legge — né
    indirizzi IP, né browser, né cookie — e quindi non può sapere se due
    letture sono della stessa persona. Restano fuori i programmi che si
    dichiarano tali, le tue visite da loggato, feed e sitemap. «Da fuori»
    sono le letture arrivate da un altro sito o da nessuno: chi entra,
    più che chi passa da una pagina all'altra.
  </p>

  <?php if ($manca): ?>
    <p class="vuoto">
      La tabella delle visite non c'è ancora: va creata eseguendo
      <code>sql/visite.sql</code>. Da quel momento si comincia a contare.
    </p>
  <?php else: ?>

  <div class="costi-riquadri">
    <?php foreach ($totali as $nome => $t): ?>
      <div class="costo-riquadro<?= $nome === 'ultimi 30 gg' ? ' costo-forte' : '' ?>">
        <span class="costo-nome"><?= e($nome) ?></span>
        <span class="costo-cifra"><?= e($mila($t['v'])) ?></span>
        <span class="costo-sotto"><?= e($mila($t['fuori'])) ?> da fuori</span>
      </div>
    <?php endforeach ?>
  </div>

  <h1 class="titoletto">ultimi trenta giorni</h1>
  <div class="costi-giorni">
    <?php foreach ($giorni as $g => $v): ?>
      <?php
        // Come nei costi: il minimo di due punti dice "quel giorno c'è".
        $largo = $picco > 0 ? max(2, (int)round($v / $picco * 100)) : 2;
      ?>
      <div class="costo-giorno">
        <span class="costo-data"><?= e(date('d/m', strtotime($g))) ?></span>
        <span class="costo-barra"><i style="width: <?= $largo ?>%"></i></span>
        <span class="costo-valore"><?= e($mila($v)) ?></span>
      </div>
    <?php endforeach ?>
  </div>

  <h1 class="titoletto">le pagine più lette, negli ultimi trenta giorni</h1>
  <?php if (!$pagine): ?>
    <p class="vuoto">Ancora nessuna lettura.</p>
  <?php else: ?>
    <div class="bozza registro">
      <?php foreach ($pagine as $p): ?>
        <?php
          $percorso = (string)$p['percorso'];
          $slug = preg_match('#^/notizie/([a-z0-9-]+)$#', $percorso, $m) ? $m[1] : null;
          $nome = $slug !== null && isset($titoli[$slug]) ? $titoli[$slug]
                : ($percorso === '/' ? 'la home' : $percorso);
          // Stessa convenzione degli indirizzi del sito: sezioni e
          // schede con lo slash in fondo, pagine singole senza.
          $link = $percorso === '/' ? u('/')
                : u(implode('/', array_map('rawurlencode', explode('/', ltrim($percorso, '/')))))
                  . (preg_match('#^/(?:notizie|privacy|cerca)$#', $percorso) ? '' : '/');
        ?>
        <div>
          <strong><?= e($mila((int)$p['v'])) ?></strong> ·
          <a href="<?= e($link) ?>"><?= e($nome) ?></a>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <h1 class="titoletto">da dove si arriva, negli ultimi trenta giorni</h1>
  <?php if (!$origini): ?>
    <p class="vuoto">Ancora nessun arrivo da fuori.</p>
  <?php else: ?>
    <div class="bozza registro">
      <?php foreach ($origini as $o): ?>
        <div>
          <strong><?= e($mila((int)$o['v'])) ?></strong> ·
          <?= $o['origine'] === ''
              ? 'nessuna provenienza — indirizzo scritto a mano, segnalibro, app'
              : e((string)$o['origine']) ?>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <?php endif ?>
</div>
