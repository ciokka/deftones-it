<div class="pannello">
  <p><a class="torna" href="<?= u('admin/') ?>"><?= icona('indietro') ?> torna alle bozze</a></p>

  <?php $nuovo = ($a['stato'] ?? '') === 'nuovo'; ?>
  <h1 class="titoletto"><?= $nuovo ? 'Nuovo articolo' : 'Modifica' ?></h1>

  <?php /* avviso() e non un <p> scritto a mano: quando il messaggio si
           porta dietro un lavoro da seguire — «migliora» lo fa — ci
           attacca sotto il riquadro che mostra il log riga per riga.
           Senza, un pulsante che avvia qualcosa di lungo è
           indistinguibile da un pulsante che non fa niente. */ ?>
  <?= avviso($messaggio) ?>

  <?php
  /* La proposta che aspetta, in cima e prima del modulo.
     Una revisione pronta che nessuno vede è una ricerca sul web pagata
     per niente — e peggio: intanto modifichi a mano l'articolo, e quando
     poi la applichi ti cancella il lavoro. Quindi si vede subito. */
  $viva = null;
  foreach ($revisioni as $rv) {
      if (in_array($rv['stato'], ['pronta', 'attesa', 'lavorazione'], true)) { $viva = $rv; break; }
  }
  ?>
  <?php if ($viva && $viva['stato'] === 'pronta'): ?>
    <div class="avvisoOk revisione-pronta">
      <p><strong>C'è una versione migliorata che aspetta il tuo giudizio.</strong>
        <?php if ($viva['nota']): ?><br><em><?= e((string)$viva['nota']) ?></em><?php endif ?></p>
      <?php if ($viva['motivazione']): ?>
        <p class="revisione-motivo"><?= e(mb_substr((string)$viva['motivazione'], 0, 300)) ?><?php
          ?><?= mb_strlen((string)$viva['motivazione']) > 300 ? '…' : '' ?></p>
      <?php endif ?>
      <p><a class="bottone" href="<?= u('admin/revisione/' . (int)$viva['id']) ?>">
        <?= icona('anteprima') ?>guarda il confronto</a></p>
    </div>
  <?php /* Non quando il messaggio qui sopra si porta già dietro il
           riquadro che segue il log dal vivo: lì l'avanzamento si vede
           scorrere, e dire «ricarica la pagina» accanto a una cosa che
           si sta aggiornando da sola è un invito a interromperla. */ ?>
  <?php elseif ($viva && empty($messaggio[2])): ?>
    <div class="avvisoOk">
      <p>Rilettura in corso, chiesta <?= e(date('d/m alle H:i', strtotime((string)$viva['creato_il']))) ?>.
        Ci mette qualche minuto: ricarica la pagina fra un po'.</p>
    </div>
  <?php endif ?>

  <form method="post" action="<?= $nuovo ? u('admin/nuovo') : u('admin/modifica/' . (int)$a['id']) ?>" class="modulo">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">

    <label class="campo-largo">
      <span>Titolo</span>
      <input type="text" name="titolo_it" value="<?= e($a['titolo_it']) ?>" maxlength="300" required>
    </label>

    <label class="campo-largo">
      <span>Sommario <em>— è quello che si legge negli elenchi e nelle anteprime social</em></span>
      <textarea name="sommario_it" rows="4"><?= e($a['sommario_it']) ?></textarea>
    </label>

    <label class="campo-largo">
      <span>Corpo <em>— HTML. Vuoto per le notizie brevi, che mostrano il solo sommario</em></span>
      <textarea name="corpo_it" rows="18" spellcheck="false"><?= e((string)$a['corpo_it']) ?></textarea>
    </label>

    <div class="modulo-riga">
      <label>
        <span>Categoria</span>
        <select name="categoria">
          <?php foreach ($categorie as $c): ?>
            <option value="<?= e($c) ?>" <?= $a['categoria'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach ?>
        </select>
      </label>

      <label>
        <span>Attendibilità</span>
        <select name="attendibilita">
          <?php foreach ($attendib as $x): ?>
            <option value="<?= e($x) ?>" <?= $a['attendibilita'] === $x ? 'selected' : '' ?>><?= e($x) ?></option>
          <?php endforeach ?>
        </select>
      </label>

      <label>
        <span>Data di pubblicazione <em>— sposta l'articolo nell'ordine e negli archivi</em></span>
        <?php /* La data che si vede sul sito. Cambiarla sposta l'articolo
                 nell'ordine cronologico e negli archivi per anno. */ ?>
        <input type="datetime-local" name="pubblicato_il"
               value="<?= $a['pubblicato_il'] ? e(str_replace(' ', 'T', mb_substr((string)$a['pubblicato_il'], 0, 16))) : '' ?>">
      </label>
    </div>

    <label class="campo-largo">
      <span>Tag <em>— separati da virgola</em></span>
      <input type="text" name="tag"
             value="<?= e(implode(', ', json_decode((string)$a['tag'], true) ?: [])) ?>">
    </label>

    <?php /* L'unica etichetta che mette una persona: il resto — rilevanza,
             categoria, attendibilità — lo decide il modello quando scrive. */ ?>
    <label class="scelta scelta-riga">
      <input type="checkbox" name="speciale" value="1" <?= $a['speciale'] ? 'checked' : '' ?>>
      <span>special <em>— il bollo che metti tu, per i pezzi che contano
        per una ragione che un punteggio non sa vedere</em></span>
    </label>

    <div class="modulo-riga">
      <label>
        <span>Fonte</span>
        <input type="text" name="fonte_nome" value="<?= e((string)$a['fonte_nome']) ?>">
      </label>
      <label class="cresce">
        <span>Indirizzo della fonte</span>
        <input type="url" name="fonte_url" value="<?= e((string)$a['fonte_url']) ?>">
      </label>
    </div>

    <?php /* La richiesta specifica sta attaccata al pulsante che la usa.
             Chiusa di suo — nove volte su dieci non serve dire niente, e
             un campo sempre aperto sotto le azioni sembra obbligatorio —
             ma «migliora con IA» la apre da sé al primo clic e avvia solo
             al secondo. Un pieghevole che nessuno apre è un campo che non
             esiste: prima, chi non ci aveva fatto caso si accorgeva di
             avere una richiesta da fare dopo aver già pagato la rilettura.

             Quando sai già cosa manca all'articolo — «controlla la data
             di lancio», «manca la posizione dell'ESA» — dirlo vale più di
             qualunque istruzione generica nel prompt, ed è l'unica cosa
             che il modello non può dedurre da solo. */ ?>
    <?php if (!$nuovo): ?>
      <details class="indicazioni-rilettura" id="indicazioni-ia">
        <summary>di' all'IA cosa controllare o cambiare <em>— facoltativo</em></summary>
        <label class="campo-largo">
          <span>La tua richiesta, se ne hai una precisa</span>
          <textarea name="indicazioni" id="campo-indicazioni" rows="2" maxlength="500"
                    placeholder="per esempio: verifica la data di lancio, e vedi se l'ESA ha detto qualcosa"></textarea>
        </label>
        <p class="indicazioni-nota" id="nota-indicazioni" hidden>
          Scrivi la richiesta, poi premi di nuovo per avviare. Senza indicazioni
          il modello decide da sé cosa vale la pena migliorare.</p>
      </details>
    <?php endif ?>

    <div class="barra-azioni">
      <?php if ($nuovo): ?>
        <?php /* Tre modi di finire, perché sono tre intenzioni diverse:
                 metterlo da parte, metterlo online, metterlo online già
                 illustrato. L'ultimo ci mette qualche secondo perché
                 scarica davvero la fotografia. */ ?>
        <button class="bottone" type="submit" name="come" value="copertina">
          <?= icona('immagine') ?>pubblica con copertina</button>
        <button class="bottone bottone-tenue" type="submit" name="come" value="pubblica">
          <?= icona('pubblica') ?>pubblica</button>
        <button class="bottone bottone-tenue" type="submit" name="come" value="bozza">
          <?= icona('salva') ?>salva come bozza</button>
      <?php else: ?>
        <button class="bottone" type="submit"><?= icona('salva') ?>salva</button>
        <?php /* Salva anche questo, e poi mette in coda la rilettura: il
                 modello deve leggere l'articolo che hai davanti, non
                 quello che c'era nel database prima delle modifiche che
                 stai ancora guardando.

                 La conferma c'è perché questo pulsante spende — è la
                 stessa regola di «cerca notizie» nell'elenco — e perché
                 dura minuti, che su un pannello dove tutto il resto è
                 istantaneo va detto prima e non dopo.

                 Il confirm scritto qui è la rete per il browser senza
                 JavaScript. Dove c'è, lo script in fondo lo toglie e
                 mette i due tempi: il primo clic apre le indicazioni,
                 il secondo avvia — e la conferma ripete la richiesta
                 scritta, che è l'ultimo momento buono per accorgersi
                 che si sta chiedendo la cosa sbagliata. */ ?>
        <button class="bottone bottone-tenue" type="submit" name="come" value="migliora"
                id="bottone-migliora"
                <?= $viva ? 'disabled title="c\'è già una rilettura in corso o una proposta da guardare"' : '' ?>
                onclick="return confirm('Salvo, poi il modello rilegge l\'articolo, cerca sul web cos\'è successo dopo e propone una versione nuova.\n\nCi mette qualche minuto e costa qualche centesimo. Niente viene sovrascritto: la proposta te la mostro prima.\n\nProcedo?')">
          <?= icona('migliora') ?><span id="testo-migliora">migliora con IA</span></button>
        <a class="bottone bottone-tenue" href="<?= u('admin/copertina/' . (int)$a['id']) ?>">
          <?= icona('immagine') ?>copertina</a>
        <?php if ($a['stato'] === 'pubblicato'): ?>
          <a class="bottone bottone-tenue" href="<?= u('notizie/' . $a['slug'] . '/') ?>"
             target="_blank" rel="noopener"><?= icona('fuori') ?>vedi online</a>
        <?php else: ?>
          <a class="bottone bottone-tenue" href="<?= u('admin/anteprima/' . (int)$a['id']) ?>">
            <?= icona('anteprima') ?>anteprima</a>
        <?php endif ?>
        <span class="modulo-nota">
          stato: <?= e($a['stato']) ?><?php if ($a['immagine_origine']): ?> · copertina: <?= e($a['immagine_origine']) ?><?php endif ?>
        </span>
      <?php endif ?>
    </div>
  </form>
  <?php /* Le riletture già fatte. Serve a rispondere a «questo articolo
           l'ho già fatto rivedere?», che è la domanda per cui uno
           altrimenti ne chiede una seconda uguale — e la paga. Le
           motivazioni restano leggibili anche dopo: sono il registro di
           cosa è cambiato e perché, che sull'articolo non si vede. */ ?>
  <?php $passate = array_filter($revisioni, fn($rv) => in_array($rv['stato'],
        ['applicata', 'scartata', 'errore'], true)); ?>
  <?php if ($passate): ?>
    <details class="revisioni-passate">
      <summary><?= count($passate) ?>
        <?= count($passate) === 1 ? 'rilettura già fatta' : 'riletture già fatte' ?></summary>
      <ul class="revisioni-elenco">
        <?php foreach ($passate as $rv): ?>
          <li>
            <span class="revisione-stato revisione-<?= e($rv['stato']) ?>"><?= e($rv['stato']) ?></span>
            <span class="revisione-quando">
              <?= e(date('d/m/Y', strtotime((string)$rv['creato_il']))) ?>
              <?php if ((int)$rv['token_in'] + (int)$rv['token_out'] > 0): ?>
                · <?= e(number_format(costoEuro((int)$rv['token_in'], (int)$rv['token_out']), 2, ',', '.')) ?> €
              <?php endif ?>
            </span>
            <?php if ($rv['indicazioni']): ?>
              <span class="revisione-chiesto">«<?= e((string)$rv['indicazioni']) ?>»</span>
            <?php endif ?>
            <?php if ($rv['stato'] === 'errore'): ?>
              <span class="revisione-motivo"><?= e((string)$rv['nota']) ?></span>
            <?php elseif ($rv['motivazione']): ?>
              <span class="revisione-motivo"><?= e((string)$rv['motivazione']) ?></span>
            <?php endif ?>
            <a href="<?= u('admin/revisione/' . (int)$rv['id']) ?>">confronto</a>
          </li>
        <?php endforeach ?>
      </ul>
    </details>
  <?php endif ?>
</div>

<?php if (!$nuovo): ?>
<script>
// «migliora con IA» in due tempi. Il campo delle indicazioni c'era già,
// ma stava chiuso in un pieghevole: chi non lo apriva prima si accorgeva
// di avere una richiesta da fare quando la rilettura era già partita —
// e una rilettura partita costa e non si annulla. Così il primo clic non
// avvia niente: apre il campo e ci mette il cursore. Avvia il secondo.
//
// Chi la richiesta l'ha già scritta salta il primo tempo: il pulsante
// avvia subito, altrimenti diventerebbe un clic in più per tutti.
(function () {
  var bottone = document.getElementById('bottone-migliora');
  var scatola = document.getElementById('indicazioni-ia');
  var campo   = document.getElementById('campo-indicazioni');
  var nota    = document.getElementById('nota-indicazioni');
  var testo   = document.getElementById('testo-migliora');
  if (!bottone || !scatola || !campo) { return; }

  // La rete per chi non ha JavaScript non serve più a chi ce l'ha, e
  // lasciarla vorrebbe dire due conferme di fila.
  bottone.removeAttribute('onclick');
  var armato = false;

  bottone.addEventListener('click', function (ev) {
    var chiesto = campo.value.trim();

    if (!armato && chiesto === '') {
      ev.preventDefault();
      armato = true;
      scatola.open = true;
      if (nota) { nota.hidden = false; }
      if (testo) { testo.textContent = 'avvia la rilettura'; }
      campo.focus();
      return;
    }

    var d = 'Salvo, poi il modello rilegge l\'articolo, cerca sul web cos\'è '
          + 'successo dopo e propone una versione nuova.\n\n'
          + (chiesto !== ''
              ? 'Gli chiedi: «' + chiesto + '»\n\n'
              : 'Senza indicazioni: decide da sé cosa vale la pena migliorare.\n\n')
          + 'Ci mette qualche minuto e costa qualche centesimo. Niente viene '
          + 'sovrascritto: la proposta te la mostro prima.\n\nProcedo?';
    if (!confirm(d)) { ev.preventDefault(); }
  });
})();
</script>
<?php endif ?>
