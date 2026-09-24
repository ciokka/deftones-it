<?php
/**
 * admin-revisione.php — il confronto fra un articolo e la versione che
 * il modello ne propone.
 *
 * Tutta la pagina serve a una decisione sola, e a renderla informata.
 * Per questo il «prima» e il «dopo» stanno affiancati con le differenze
 * marcate, e per questo «applica» non è il pulsante grande in cima: da
 * qui si sovrascrive un articolo, e la fretta è la cosa da non favorire.
 */
$tagPrima = json_decode((string)$r['prima_tag'], true) ?: [];
$tagDopo  = json_decode((string)$r['tag'], true) ?: [];
$fonti    = json_decode((string)$r['fonti'], true) ?: [];

/* L'articolo è cambiato da quando la rilettura è partita?
   Fra la richiesta e adesso passano minuti, e in quei minuti si può
   aver corretto qualcosa a mano. Applicare la proposta cancellerebbe
   quelle correzioni in silenzio: è il solo modo in cui questa pagina
   può far perdere del lavoro, e quindi è l'unica cosa che deve
   gridare. */
$scostato = $r['stato'] === 'pronta' && (
       (string)$r['prima_titolo']   !== (string)$r['ora_titolo']
    || (string)$r['prima_sommario'] !== (string)$r['ora_sommario']
    || (string)$r['prima_corpo']    !== (string)$r['ora_corpo']);

[$titoloSx, $titoloDx]     = diffParole((string)$r['prima_titolo'], (string)$r['titolo_it']);
[$sommarioSx, $sommarioDx] = diffParole((string)$r['prima_sommario'], (string)$r['sommario_it']);
$avevaCorpo = trim((string)$r['prima_corpo']) !== '';
$avraCorpo  = trim((string)$r['corpo_it']) !== '';
?>
<div class="pannello">
  <p><a class="torna" href="<?= u('admin/modifica/' . (int)$r['articolo_id']) ?>">
    <?= icona('indietro') ?> torna all'articolo</a></p>

  <h1 class="titoletto"><?= icona('migliora', 18) ?> Revisione proposta</h1>

  <?= avviso($messaggio) ?>

  <?php if ($r['stato'] === 'errore'): ?>
    <div class="avvisoKo">
      <p><strong>La rilettura non è riuscita.</strong> <?= e((string)$r['nota']) ?></p>
      <p>L'articolo non è stato toccato. Puoi richiederla dalla pagina dell'articolo.</p>
    </div>
  <?php elseif (in_array($r['stato'], ['attesa', 'lavorazione'], true)): ?>
    <div class="avvisoOk">
      <p>Rilettura ancora in corso. Ricarica fra un minuto.</p>
    </div>
  <?php else: ?>

    <?php if ($r['stato'] !== 'pronta'): ?>
      <div class="avvisoOk">
        <p>Questa proposta è già stata <strong><?= e($r['stato']) ?></strong>
          <?php if ($r['elaborato_il']): ?>
            il <?= e(date('d/m/Y', strtotime((string)$r['elaborato_il']))) ?>
          <?php endif ?>.
          Resta qui come registro di cosa era stato proposto.</p>
      </div>
    <?php endif ?>

    <?php if ($scostato): ?>
      <div class="avvisoKo">
        <p><strong>Attenzione: l'articolo è cambiato dopo che la rilettura era partita.</strong></p>
        <p>Il modello ha lavorato sulla versione qui a sinistra, che non è più
          quella salvata. Applicando la proposta, le modifiche fatte a mano nel
          frattempo si perdono. Se erano tue e ci tieni, riportale a mano dopo —
          oppure scarta questa proposta e chiedine un'altra.</p>
      </div>
    <?php endif ?>

    <?php /* La motivazione sta in cima e fuori dal confronto perché è la
             cosa che si legge per prima e, quando è chiara, spesso
             l'unica: dice cos'è cambiato e perché. Le due colonne
             servono a verificarla, non a sostituirla. */ ?>
    <?php if ($r['motivazione']): ?>
      <div class="revisione-motivazione">
        <h2>Cosa dice di aver cambiato</h2>
        <?php if ($r['nota']): ?>
          <p class="revisione-nota"><em><?= e((string)$r['nota']) ?></em></p>
        <?php endif ?>
        <?= nl2br(e((string)$r['motivazione'])) ?>
      </div>
    <?php endif ?>

    <div class="confronto">
      <h2>Titolo</h2>
      <div class="confronto-riga">
        <div class="confronto-prima"><span class="confronto-eti">com'era</span>
          <p><?= $titoloSx ?></p></div>
        <div class="confronto-dopo"><span class="confronto-eti">come sarebbe</span>
          <p><?= $titoloDx ?></p></div>
      </div>

      <h2>Sommario</h2>
      <div class="confronto-riga">
        <div class="confronto-prima"><span class="confronto-eti">com'era</span>
          <p><?= nl2br($sommarioSx) ?></p></div>
        <div class="confronto-dopo"><span class="confronto-eti">come sarebbe</span>
          <p><?= nl2br($sommarioDx) ?></p></div>
      </div>

      <?php /* Il corpo si mostra reso, non come sorgente: è come lo
               leggerà chi arriva sul sito, ed è quello che stai
               giudicando. Il sorgente resta nel campo della pagina di
               modifica, dopo, se serve metterci mano. */ ?>
      <?php if ($avevaCorpo || $avraCorpo): ?>
        <h2>Corpo</h2>
        <div class="confronto-riga">
          <div class="confronto-prima"><span class="confronto-eti">com'era</span>
            <?php if ($avevaCorpo): ?>
              <div class="corpo"><?= (string)$r['prima_corpo'] ?></div>
            <?php else: ?>
              <p class="confronto-vuoto">Non c'era: era una notizia breve,
                tutto stava nel sommario.</p>
            <?php endif ?>
          </div>
          <div class="confronto-dopo"><span class="confronto-eti">come sarebbe</span>
            <?php if ($avraCorpo): ?>
              <div class="corpo"><?= (string)$r['corpo_it'] ?></div>
            <?php else: ?>
              <p class="confronto-vuoto">Resta una notizia breve: il modello non
                ha trovato materiale per reggere un pezzo intero.</p>
            <?php endif ?>
          </div>
        </div>
      <?php endif ?>

      <?php if ($tagPrima !== $tagDopo): ?>
        <h2>Tag</h2>
        <div class="confronto-riga">
          <div class="confronto-prima"><span class="confronto-eti">com'erano</span>
            <p><?php foreach ($tagPrima as $tg): ?><span class="rev-tag<?=
              in_array($tg, $tagDopo, true) ? '' : ' rev-tag-tolto' ?>"><?= e((string)$tg) ?></span><?php endforeach ?></p></div>
          <div class="confronto-dopo"><span class="confronto-eti">come sarebbero</span>
            <p><?php foreach ($tagDopo as $tg): ?><span class="rev-tag<?=
              in_array($tg, $tagPrima, true) ? '' : ' rev-tag-messo' ?>"><?= e((string)$tg) ?></span><?php endforeach ?></p></div>
        </div>
      <?php endif ?>
    </div>

    <?php /* Le pagine consultate, per esteso e cliccabili. Sono il
             motivo per cui questa proposta si può credere: una revisione
             senza fonti è un'opinione, e va guardata come tale. */ ?>
    <?php if ($fonti): ?>
      <details class="revisione-fonti" open>
        <summary><?= count($fonti) ?> pagine consultate</summary>
        <ul>
          <?php foreach ($fonti as $url => $titolo): ?>
            <li><a href="<?= e((string)$url) ?>" target="_blank" rel="noopener">
              <?= e((string)$titolo) ?></a></li>
          <?php endforeach ?>
        </ul>
      </details>
    <?php endif ?>

    <?php if ($r['stato'] === 'pronta'): ?>
      <div class="barra-azioni">
        <?php /* «applica» sovrascrive l'articolo: la conferma c'è perché
                 è l'unica azione irreversibile di questa pagina, e
                 perché su un pezzo già online si vede subito fuori. */ ?>
        <form method="post" action="<?= u('admin/revisione/' . (int)$r['id']) ?>" style="display:inline"
              onsubmit="return confirm('Sostituisco titolo, sommario, corpo e tag dell\'articolo con la versione proposta.\n\nL\'indirizzo della pagina non cambia. La versione di adesso non si recupera.\n\nProcedo?')">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="che" value="applica">
          <button class="bottone" type="submit"><?= icona('pubblica') ?>applica la revisione</button>
        </form>
        <form method="post" action="<?= u('admin/revisione/' . (int)$r['id']) ?>" style="display:inline">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="che" value="scarta">
          <button class="bottone bottone-tenue" type="submit"><?= icona('scarta') ?>scarta</button>
        </form>
        <a class="bottone bottone-tenue" href="<?= u('admin/modifica/' . (int)$r['articolo_id']) ?>">
          <?= icona('modifica') ?>decidi dopo</a>
      </div>
    <?php endif ?>

  <?php endif ?>

  <?php /* Quanto è costata. Piccolo e in fondo, ma scritto: una funzione
           che spende e non dice quanto si usa senza pensarci, ed è così
           che una bolletta diventa una sorpresa. */ ?>
  <p class="modulo-nota">
    chiesta il <?= e(date('d/m/Y \a\l\l\e H:i', strtotime((string)$r['creato_il']))) ?>
    <?php if ((int)$r['token_in'] + (int)$r['token_out'] > 0): ?>
      · <?= (int)$r['token_in'] ?> token in, <?= (int)$r['token_out'] ?> out
      · circa <?= e(number_format(costoEuro((int)$r['token_in'], (int)$r['token_out']), 2, ',', '.')) ?> €
    <?php endif ?>
    <?php if ($r['modello']): ?> · <?= e((string)$r['modello']) ?><?php endif ?>
    <?php if ($r['indicazioni']): ?>
      <br>indicazioni date: «<?= e((string)$r['indicazioni']) ?>»
    <?php endif ?>
  </p>
</div>
