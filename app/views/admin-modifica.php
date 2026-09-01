<div class="pannello">
  <p><a class="torna" href="<?= u('admin/') ?>"><?= icona('indietro') ?> torna alle bozze</a></p>

  <?php $nuovo = ($a['stato'] ?? '') === 'nuovo'; ?>
  <h1 class="titoletto"><?= $nuovo ? 'Nuovo articolo' : 'Modifica' ?></h1>

  <?php if ($messaggio): ?>
    <p class="avviso<?= $messaggio[0] === 'ok' ? 'Ok' : 'Ko' ?>"><?= e($messaggio[1]) ?></p>
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
</div>
