<?php
/** L'indirizzo di questa pagina conservando i filtri. */
$link = function (array $cambia = []) use ($stato, $sog, $cerca): string {
    $p = array_filter(['stato' => $stato, 'sog' => $sog, 'q' => $cerca] + $cambia,
                      fn($v) => $v !== '' && $v !== null);
    $p = array_merge($p, array_filter($cambia, fn($v) => $v !== null));
    $p = array_filter($p, fn($v) => $v !== '');
    return u('admin/foto') . ($p ? '?' . http_build_query($p) : '');
};
$query = (string)parse_url($link(), PHP_URL_QUERY);
?>
<div class="pannello">
  <p><a class="torna" href="<?= u('admin/') ?>"><?= icona('indietro') ?> torna alle bozze</a></p>

  <?php if ($messaggio): ?>
    <p class="avviso<?= $messaggio[0] === 'ok' ? 'Ok' : 'Ko' ?>"><?= e($messaggio[1]) ?></p>
  <?php endif ?>

  <div class="pannello-testa">
    <h1>fotografie <span class="conta"><?= (int)$conti['attive'] ?></span></h1>
    <div class="azioni">
      <?php foreach ([['attive', 'in uso', (int)$conti['attive']],
                      ['scartate', 'scartate', (int)$conti['scartate']],
                      ['tutte', 'tutte', (int)$conti['tutte']]] as [$k, $et, $n]): ?>
        <a class="bottone <?= $stato === $k ? 'bottone-acceso' : 'bottone-tenue' ?>"
           href="<?= e($link(['stato' => $k])) ?>"><?= $et ?> <?= $n ?></a>
      <?php endforeach ?>
    </div>
  </div>

  <p class="occhiello">
    Un clic su una fotografia la toglie dal catalogo, o la rimette. Le
    scartate non vengono cancellate: restano qui, così una raccolta futura
    non le riporta dentro e un ripensamento costa un clic.
  </p>

  <div class="barra-raccolte">
    <?php /* Le raccolte partono staccate dalla pagina: durano troppo
             perché il browser le aspetti. Il resoconto arriva nel log
             qui sotto, che si aggiorna ricaricando. */ ?>
    <?php foreach ([['--raccogli', 'raccogli', 'Wikimedia Commons',
                     'una ventina di secondi'],
                    ['--raccogli-altre', 'raccogli', 'Openverse (Flickr)',
                     'cinque minuti — non più di una volta al giorno'],
                    ['--diagnosi', 'diagnosi', 'prova Openverse',
                     'tre richieste, per sapere se risponde']]
                  as [$modo, $ico, $et, $quanto]): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="raccolta" value="<?= e($modo) ?>">
        <input type="hidden" name="torna" value="<?= e($query ? '?' . $query : '') ?>">
        <button type="submit" class="bottone bottone-tenue" title="<?= e($quanto) ?>">
          <?= icona($ico) ?> <?= e($et) ?>
        </button>
      </form>
    <?php endforeach ?>
    <a class="bottone bottone-tenue" href="<?= e($link()) ?>" title="rileggi il resoconto">
      <?= icona('cambia') ?> aggiorna
    </a>
  </div>

  <?php if ($log !== ''): ?>
    <details class="resoconto"<?= $messaggio ? ' open' : '' ?>>
      <summary>resoconto dell'ultima raccolta</summary>
      <pre><?= e($log) ?></pre>
    </details>
  <?php endif ?>

  <div class="filtri-archivio">
    <div class="filtro-riga">
      <span class="filtro-nome">soggetto</span>
      <a class="filtro-voce<?= $sog === '' ? ' attivo' : '' ?>" href="<?= e($link(['sog' => ''])) ?>">tutti</a>
      <?php foreach ($soggetti as $s): ?>
        <a class="filtro-voce<?= $sog === $s['soggetto'] ? ' attivo' : '' ?>"
           href="<?= e($link(['sog' => $s['soggetto']])) ?>"><?= e($s['soggetto']) ?>
          <span class="filtro-n"><?= (int)$s['n'] ?></span></a>
      <?php endforeach ?>
    </div>
  </div>

  <form class="ricerca-blocco" method="get">
    <input type="hidden" name="stato" value="<?= e($stato) ?>">
    <input type="hidden" name="sog" value="<?= e($sog) ?>">
    <div class="ricerca">
      <input type="search" name="q" value="<?= e($cerca) ?>" placeholder="filtra per autore…">
      <button type="submit">filtra</button>
    </div>
  </form>

  <?php if (!$foto): ?>
    <p class="vuoto">Nessuna fotografia con questi filtri.</p>
  <?php else: ?>
    <div class="griglia-foto griglia-fitta">
      <?php foreach ($foto as $f): ?>
        <form method="post" class="foto-scelta<?= $f['scartata'] ? ' foto-fuori' : '' ?>">
          <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
          <input type="hidden" name="img" value="<?= (int)$f['id'] ?>">
          <input type="hidden" name="torna" value="<?= e($query ? '?' . $query : '') ?>">
          <button type="submit" title="<?= $f['scartata'] ? 'rimetti nel catalogo' : 'togli dal catalogo' ?>">
            <img src="<?= e(miniaturaFoto((string)$f['url_file'])) ?>" alt=""
                 loading="lazy" decoding="async">
            <span class="foto-dati">
              <span class="foto-autore"><?= e(mb_substr((string)($f['autore'] ?: '—'), 0, 26)) ?></span>
              <span class="foto-uso">
                <?php if ($f['data_foto']): ?><?= e(dataFotoBreve((string)$f['data_foto'])) ?> · <?php
                      elseif (!empty($f['titolo'])): ?><?= e(mb_substr((string)$f['titolo'], 0, 24)) ?> · <?php endif ?>
                <?= (int)$f['usata'] ?> <?= (int)$f['usata'] === 1 ? 'uso' : 'usi' ?>
                <?php if ($f['soggetto'] !== 'band'): ?> · <?= e($f['soggetto']) ?><?php endif ?>
                <?php /* Una NC si vede: se un giorno il sito diventasse
                           commerciale, sono queste le foto da togliere. */ ?>
                <?php if (preg_match('/\bnc\b/i', (string)$f['licenza'])): ?>
                  <span class="tag-nc">NC</span>
                <?php endif ?>
              </span>
            </span>
          </button>
        </form>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
