<?php
/** L'indirizzo di questa pagina conservando i filtri. */
$ord = (string)($_GET['ord'] ?? '');
$link = function (array $cambia = []) use ($stato, $sog, $cerca, $ord): string {
    $p = array_filter(['stato' => $stato, 'sog' => $sog, 'q' => $cerca, 'ord' => $ord] + $cambia,
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
    <?= avviso($messaggio) ?>
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
    <?php foreach ([['raccogli', 'raccogli', 'wikimedia commons',
                     'una ventina di secondi'],
                    ['raccogli-altre', 'raccogli', 'openverse (flickr)',
                     'cinque minuti — non più di una volta al giorno'],
                    ['diagnosi', 'diagnosi', 'prova openverse',
                     'tre richieste, per sapere se risponde']]
                  as [$modo, $ico, $et, $quanto]): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="lavoro" value="<?= e($modo) ?>">
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

  <details class="caricamento">
    <summary><?= icona('immagine') ?> carica una fotografia</summary>
    <?php /* L'unica fonte davvero aggiornata: Commons ha una foto del
             2025 e Flickr nessuna, mentre l'ufficio stampa di
             un'etichetta e un fotografo di concerti hanno esattamente
             quello che serve. Basta chiedere — e poter caricare. */ ?>
    <p class="occhiello">
      Per le fotografie ottenute dall'ufficio stampa o dal permesso di un
      fotografo. Entrano nello stesso catalogo delle altre e si scelgono
      allo stesso modo. L'autore e la licenza non sono facoltativi: sono
      quello che rende lecito pubblicarla.
    </p>
    <form method="post" enctype="multipart/form-data" class="modulo-foto">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="carica" value="1">
      <input type="hidden" name="torna" value="<?= e($query ? '?' . $query : '') ?>">

      <label>fotografia (JPEG o PNG, lato lungo almeno 900px)
        <input type="file" name="foto" accept="image/jpeg,image/png" required>
      </label>
      <label>autore
        <input type="text" name="autore" maxlength="200" required
               placeholder="chi l'ha scattata">
      </label>
      <label>licenza o permesso
        <input type="text" name="licenza" maxlength="80" required
               placeholder="CC BY 4.0, oppure: permesso dell'autore">
      </label>
      <label>indirizzo della licenza <span class="facolt">facoltativo</span>
        <input type="url" name="licenza_url" maxlength="400"
               placeholder="https://creativecommons.org/licenses/by/4.0/">
      </label>
      <label>da dove viene <span class="facolt">facoltativo</span>
        <input type="url" name="url_pagina" maxlength="600"
               placeholder="la pagina originale, o il press kit">
      </label>
      <label>titolo <span class="facolt">facoltativo</span>
        <input type="text" name="titolo" maxlength="200"
               placeholder="Deftones @ Alcatraz, Milano">
      </label>
      <label>data di scatto <span class="facolt">anche solo l'anno</span>
        <input type="text" name="data_foto" maxlength="10"
               placeholder="2026-05-18, oppure 2026-05, oppure 2026">
      </label>
      <label>soggetto
        <select name="soggetto">
          <?php foreach (['band', 'chino', 'stephen', 'sergio',
                          'abe', 'frank', 'chi'] as $sg): ?>
            <option value="<?= e($sg) ?>"><?= e($sg) ?></option>
          <?php endforeach ?>
        </select>
      </label>

      <button type="submit" class="bottone bottone-acceso">
        <?= icona('salva') ?> metti in catalogo
      </button>
    </form>
  </details>

  <?php if ($log !== ''): ?>
    <details class="resoconto"<?= $messaggio ? ' open' : '' ?>>
      <summary>resoconto dell'ultima raccolta</summary>
      <pre><?= e($log) ?></pre>
    </details>
  <?php endif ?>

  <div class="filtri-archivio">
    <div class="filtro-riga">
      <span class="filtro-nome">ordine</span>
      <a class="filtro-voce<?= $ord === '' ? ' attivo' : '' ?>"
         href="<?= e($link(['ord' => ''])) ?>">per soggetto</a>
      <a class="filtro-voce<?= $ord === 'recenti' ? ' attivo' : '' ?>"
         href="<?= e($link(['ord' => 'recenti'])) ?>">ultime arrivate</a>
    </div>
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
                <?php if ($d = didascaliaFoto($f, 24)): ?><?= e($d) ?> · <?php endif ?>
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
