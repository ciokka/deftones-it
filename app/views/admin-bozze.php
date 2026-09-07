<?php
/** Costruisce un URL del pannello conservando i filtri attivi. */
$link = function (array $cambia = []) use ($cerca, $anno, $cat, $ordine, $pagina, $stato, $hot, $sp, $cop, $da, $a): string {
    $p = [
        'q' => $cerca, 'anno' => $anno ?: '', 'cat' => $cat, 'ord' => $ordine,
        'stato' => $stato === 'draft' ? '' : $stato,
        'hot' => $hot ? '1' : '', 'sp' => $sp ? '1' : '',
        'cop' => $cop, 'da' => $da, 'a' => $a,
        'p' => $pagina > 1 ? $pagina : '',
    ];
    // Cambiando un filtro si torna sempre a pagina uno: restare alla
    // sette di un elenco che ora ne ha due è il modo più veloce di
    // credere che il filtro non abbia trovato niente.
    if ($cambia && !isset($cambia['p'])) { $p['p'] = ''; }
    $p = array_merge($p, $cambia);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return u('admin/') . ($p ? '?' . http_build_query($p) : '');
};

/* I filtri correnti, da portarsi dietro nei moduli che agiscono: dopo un
   POST la querystring non c'è più, e senza questi l'elenco tornerebbe
   senza filtri e alla prima pagina a ogni clic. Solo chiavi note, da
   valori già convalidati. */
$filtriCorrenti = http_build_query(array_filter([
    'q' => $cerca, 'anno' => $anno ?: '', 'cat' => $cat, 'ord' => $ordine,
    'stato' => $stato === 'draft' ? '' : $stato,
    'hot' => $hot ? '1' : '', 'sp' => $sp ? '1' : '',
    'cop' => $cop, 'da' => $da, 'a' => $a,
    'p' => $pagina > 1 ? $pagina : '',
], fn($v) => $v !== '' && $v !== null && $v !== 0));

$etichetteStato = ['draft' => 'bozze', 'pubblicato' => 'online',
                   'scartato' => 'scartate', 'tutti' => 'tutti'];
$filtriAttivi = $cerca || $anno || $cat || $hot || $sp || $cop || $da || $a;
?>
<div class="pannello">

  <?php /* Le tre di servizio stanno sopra, in un angolo: non riguardano
           le bozze e non c'è ragione che stiano in mezzo agli strumenti
           che invece ci lavorano. */ ?>
  <div class="azioni azioni-servizio">
    <form method="post" action="<?= u('admin/azione') ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="che" value="svuota">
      <input type="hidden" name="filtri" value="<?= e($filtriCorrenti) ?>">
      <button class="bottone bottone-tenue" type="submit"><?= icona('cache') ?>cache</button>
    </form>
    <a class="bottone bottone-tenue" href="<?= u('/') ?>"><?= icona('fuori') ?>il sito</a>
    <a class="bottone bottone-tenue" href="<?= u('admin/esci') ?>"><?= icona('esci') ?>esci</a>
  </div>

  <div class="pannello-testa">
    <h1><?= e($etichetteStato[$stato]) ?>
        <span class="conta"><?= (int)$totale ?></span></h1>
    <div class="azioni">
      <a class="bottone bottone-solo-icona" href="<?= u('admin/nuovo') ?>"
         aria-label="Nuovo articolo" title="Nuovo articolo"><?= icona('nuovo', 17) ?></a>
      <a class="bottone bottone-tenue" href="<?= u('admin/richieste') ?>">richieste</a>
      <a class="bottone bottone-tenue" href="<?= u('admin/raccolte') ?>">raccolte</a>
      <a class="bottone bottone-tenue" href="<?= u('admin/foto') ?>"><?= icona('immagine') ?>foto</a>
      <a class="bottone bottone-tenue" href="<?= u('admin/costi') ?>"><?= icona('costi') ?>costi</a>
      <?php /* Il recupero dell'archivio non chiama l'IA e non spende:
               apre duecento pagine per volta e riempie la coda. Perciò
               niente conferma — quella la chiede "cerca notizie", che
               invece scrive. */ ?>
      <form method="post" action="<?= u('admin/azione') ?>">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="che" value="lavoro">
        <input type="hidden" name="quale" value="archivio">
        <input type="hidden" name="filtri" value="<?= e($filtriCorrenti) ?>">
        <button class="bottone bottone-tenue" type="submit"
                title="cerca sulla Wayback Machine gli articoli del 2021-2025 e riempie la coda"><?= icona('raccogli') ?>archivio</button>
      </form>
      <?php /* La conferma c'è perché questo è l'unico pulsante del
               pannello che spende soldi — qualche centesimo, ma il cron
               lo fa già da sé ogni quattro ore, e cliccarlo per
               abitudine sarebbe pagare due volte. */ ?>
      <form method="post" action="<?= u('admin/azione') ?>" style="display:inline"
            onsubmit="return confirm('Cerca notizie nuove e scrive le bozze. Costa qualche centesimo di API. Procedo?')">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="che" value="lavoro">
        <input type="hidden" name="quale" value="novita">
        <input type="hidden" name="filtri" value="<?= e($filtriCorrenti) ?>">
        <button class="bottone bottone-tenue" type="submit"
                title="ingest e poi enrich: cerca notizie e scrive le bozze"><?= icona('raccogli') ?>cerca notizie</button>
      </form>
    </div>
  </div>

  <?php if ($messaggio): ?>
    <?= avviso($messaggio) ?>
  <?php endif ?>

  <?php /* Le linguette portano i conteggi assoluti, non quelli filtrati:
           servono a decidere dove andare, e un numero che cambia con i
           filtri non risponderebbe a quella domanda. */ ?>
  <nav class="linguette">
    <?php foreach ($etichetteStato as $k => $et): ?>
      <a class="linguetta<?= $stato === $k ? ' attiva' : '' ?>"
         href="<?= e($link(['stato' => $k === 'draft' ? '' : $k, 'anno' => '', 'cat' => ''])) ?>">
        <?= e($et) ?><span class="linguetta-n"><?= (int)$conta[$k] ?></span></a>
    <?php endforeach ?>
  </nav>

  <form method="get" action="<?= u('admin/') ?>" class="filtri">
    <?php /* Lo stato viaggia con il modulo, o filtrare da "online"
             riporterebbe alle bozze. */ ?>
    <?php if ($stato !== 'draft'): ?><input type="hidden" name="stato" value="<?= e($stato) ?>"><?php endif ?>

    <input type="search" name="q" value="<?= e($cerca) ?>" placeholder="cerca nel titolo…">

    <select name="cat" aria-label="Categoria">
      <option value="">tutte le categorie</option>
      <?php foreach ($categorie as $c): ?>
        <option value="<?= e($c['categoria']) ?>" <?= $cat === $c['categoria'] ? 'selected' : '' ?>>
          <?= e($c['categoria']) ?> (<?= (int)$c['n'] ?>)
        </option>
      <?php endforeach ?>
    </select>

    <select name="anno" aria-label="Anno">
      <option value="">tutti gli anni</option>
      <?php foreach ($anni as $y): ?>
        <option value="<?= (int)$y['a'] ?>" <?= $anno === (int)$y['a'] ? 'selected' : '' ?>>
          <?= (int)$y['a'] ?> (<?= (int)$y['n'] ?>)
        </option>
      <?php endforeach ?>
    </select>

    <select name="cop" aria-label="Copertina">
      <option value="">con o senza foto</option>
      <option value="con"   <?= $cop === 'con'   ? 'selected' : '' ?>>con foto</option>
      <option value="senza" <?= $cop === 'senza' ? 'selected' : '' ?>>senza foto (<?= (int)$senzaCopertina ?>)</option>
    </select>

    <select name="ord" aria-label="Ordinamento">
      <?php foreach (['rilevanza' => 'per rilevanza', 'recenti' => 'i più recenti',
                      'vecchi' => 'i più vecchi', 'lunghi' => 'i più lunghi',
                      'titolo' => 'per titolo'] as $k => $et): ?>
        <option value="<?= $k ?>" <?= $ordine === $k ? 'selected' : '' ?>><?= $et ?></option>
      <?php endforeach ?>
    </select>

    <?php /* Le date si sommano all'anno invece di scavalcarlo: scegliere
             il 2013 e poi "dal 1º giugno" dà i mesi da giugno in poi di
             quell'anno, che è quello che uno si aspetta. */ ?>
    <label class="filtro-data">dal <input type="date" name="da" value="<?= e($da) ?>"></label>
    <label class="filtro-data">al <input type="date" name="a" value="<?= e($a) ?>"></label>

    <label class="scelta scelta-hot">
      <input type="checkbox" name="hot" value="1" <?= $hot ? 'checked' : '' ?>>
      solo hot <span class="linguetta-n"><?= (int)$quantiHot ?></span>
    </label>

    <label class="scelta scelta-speciale">
      <input type="checkbox" name="sp" value="1" <?= $sp ? 'checked' : '' ?>>
      solo special <span class="linguetta-n"><?= (int)$quantiSpec ?></span>
    </label>

    <button class="bottone bottone-tenue" type="submit">filtra</button>
    <?php if ($filtriAttivi): ?>
      <a class="bottone bottone-tenue" href="<?= e($link(['q' => '', 'anno' => '', 'cat' => '',
          'hot' => '', 'sp' => '', 'cop' => '', 'da' => '', 'a' => ''])) ?>">azzera</a>
    <?php endif ?>
  </form>

  <?php if (!$bozze): ?>
    <p class="vuoto">Nessun articolo con questi filtri.</p>
  <?php else: ?>

  <form method="post" action="<?= u('admin/azione') ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="che" value="multi">
    <input type="hidden" name="stato" value="<?= e($stato) ?>">
    <input type="hidden" name="filtri" value="<?= e($filtriCorrenti) ?>">

    <table class="tabella">
      <thead>
        <tr>
          <th class="c-sel"><input type="checkbox" id="tutti" aria-label="Seleziona tutti"></th>
          <th class="c-pt"><a href="<?= e($link(['ord' => 'rilevanza'])) ?>" title="ordina per rilevanza">pt</a></th>
          <th class="c-cop"><span class="sr">foto</span></th>
          <th><a href="<?= e($link(['ord' => 'titolo'])) ?>">titolo</a></th>
          <th class="c-data"><a href="<?= e($link(['ord' => 'recenti'])) ?>">data</a></th>
          <th class="c-azioni"><span class="sr">azioni</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bozze as $b): ?>
          <?php $q = $b['pubblicato_il'] ?: $b['creato_il']; ?>
          <tr class="riga-<?= e($b['stato']) ?>">
            <td class="c-sel">
              <input type="checkbox" name="ids[]" value="<?= (int)$b['id'] ?>"
                     aria-label="Seleziona <?= e(mb_substr($b['titolo_it'], 0, 60)) ?>">
            </td>

            <td class="c-pt"><span class="punteggio<?= (int)$b['rilevanza'] >= HOT_DA ? ' punteggio-hot' : '' ?>"><?= (int)$b['rilevanza'] ?></span></td>

            <td class="c-cop">
              <?php /* La miniatura vera e non un pallino: si vede in un
                       colpo d'occhio non solo che la foto c'è, ma se è
                       quella giusta. */ ?>
              <a href="<?= u('admin/copertina/' . (int)$b['id']) ?>"
                 class="mini<?= $b['immagine_url'] ? '' : ' mini-vuota' ?>"
                 title="<?= $b['immagine_url'] ? 'cambia copertina' : 'nessuna copertina' ?>">
                <?php if ($b['immagine_url']): ?>
                  <img src="<?= e($b['immagine_url']) ?>" alt="" loading="lazy" decoding="async">
                <?php else: ?><?= icona('immagine', 13) ?><?php endif ?>
              </a>
            </td>

            <td class="c-titolo">
              <a class="titolo-riga" href="<?= u('admin/anteprima/' . (int)$b['id']) ?>"><?= e($b['titolo_it']) ?></a>
              <div class="sottoriga">
                <?php /* Su schermo stretto le colonne del punteggio e
                         della data spariscono: qui ricompaiono, che è
                         meno peggio che perderle. */ ?>
                <span class="solo-stretto punteggio<?= (int)$b['rilevanza'] >= HOT_DA ? ' punteggio-hot' : '' ?>"><?= (int)$b['rilevanza'] ?></span>
                <span class="solo-stretto"><?= e(dataBreve($q)) ?></span>
                <?php if ($b['speciale']): ?><span class="etichetta et-speciale">special</span><?php endif ?>
                <span class="etichetta et-<?= e($b['categoria']) ?>"><?= e($b['categoria']) ?></span>
                <?php if ($b['attendibilita'] !== 'confermato'): ?>
                  <span class="etichetta et-dubbio"><?= e($b['attendibilita']) ?></span>
                <?php endif ?>
                <?php if ($b['stato'] === 'pubblicato' && $b['in_apertura']): ?>
                  <span class="etichetta et-apertura">in apertura</span>
                <?php endif ?>
                <?php if ($stato === 'tutti'): ?>
                  <span class="stato-punto stato-<?= e($b['stato']) ?>"><?= e($b['stato'] === 'draft' ? 'bozza' : $b['stato']) ?></span>
                <?php endif ?>
                <?php if ($b['fonte_nome']): ?><span><?= e($b['fonte_nome']) ?></span><?php endif ?>
                <?php if ((int)$b['lunghezza'] > 0): ?>
                  <span><?= number_format((int)$b['lunghezza'] / 1000, 1, ',', '.') ?>k</span>
                <?php endif ?>
              </div>
            </td>

            <td class="c-data"><time datetime="<?= e((string)$q) ?>"><?= e(dataBreve($q)) ?></time></td>

            <td class="c-azioni">
              <button class="azione<?= $b['speciale'] ? ' accesa' : '' ?>" type="submit"
                      name="specialeId" value="<?= (int)$b['id'] ?>"
                      title="<?= $b['speciale'] ? 'togli special' : 'segna come special' ?>"><?= icona('speciale', 14) ?></button>
              <a class="azione" href="<?= u('admin/modifica/' . (int)$b['id']) ?>"
                 title="modifica"><?= icona('modifica', 14) ?></a>
              <?php if ($b['stato'] === 'pubblicato'): ?>
                <a class="azione" href="<?= u('notizie/' . $b['slug'] . '/') ?>" target="_blank"
                   rel="noopener" title="vedi online"><?= icona('fuori', 14) ?></a>
                <button class="azione<?= $b['in_apertura'] ? ' accesa' : '' ?>" type="submit"
                        name="aperturaId" value="<?= (int)$b['id'] ?>"
                        title="<?= $b['in_apertura'] ? 'togli dall\'apertura' : 'metti in apertura' ?>"><?= icona('apertura', 14) ?></button>
                <button class="azione" type="submit" name="ritiraId" value="<?= (int)$b['id'] ?>"
                        title="ritira fra le bozze"><?= icona('ritira', 14) ?></button>
              <?php elseif ($b['stato'] === 'scartato'): ?>
                <button class="azione" type="submit" name="ripristinaId" value="<?= (int)$b['id'] ?>"
                        title="rimetti fra le bozze"><?= icona('cambia', 14) ?></button>
              <?php else: ?>
                <?php if ($b['fonte_url']): ?>
                  <a class="azione" href="<?= e($b['fonte_url']) ?>" target="_blank"
                     rel="noopener" title="la fonte"><?= icona('fuori', 14) ?></a>
                <?php endif ?>
                <button class="azione" type="submit" name="scartaId" value="<?= (int)$b['id'] ?>"
                        title="scarta: non viene cancellata, resta fra le scartate"><?= icona('scarta', 14) ?></button>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>

    <div class="barra-azioni">
      <span class="selezionati"><span id="conta-sel">0</span> selezionati</span>
      <?php /* Dalle scartate non si pubblica: si rimette in bozza e poi si
               decide. Un salto diretto da "scartata" a "online" salterebbe
               anche la rilettura, che è il momento in cui ci si accorge
               del perché era stata scartata. */ ?>
      <?php if ($stato === 'scartato'): ?>
        <button class="bottone" type="submit" name="come" value="ripristina">rimetti fra le bozze</button>
      <?php else: ?>
        <button class="bottone" type="submit" name="come" value="pubblica">pubblica</button>
        <button class="bottone bottone-tenue" type="submit" name="come" value="scarta">scarta</button>
      <?php endif ?>
    </div>
  </form>

  <?php if ($pagine > 1): ?>
    <nav class="paginazione">
      <?php if ($pagina > 1): ?><a href="<?= e($link(['p' => $pagina - 1])) ?>">← indietro</a><?php endif ?>
      <span>pagina <?= $pagina ?> di <?= $pagine ?></span>
      <?php if ($pagina < $pagine): ?><a href="<?= e($link(['p' => $pagina + 1])) ?>">avanti →</a><?php endif ?>
    </nav>
  <?php endif ?>

  <?php endif ?>

  <?php if ((int)$coda['n'] > 0): ?>
    <p class="coda-stato">
      <strong><?= (int)$coda['n'] ?></strong>
      <?= (int)$coda['n'] === 1 ? 'articolo in coda' : 'articoli in coda' ?>,
      da elaborare
      <?php if ($coda['piuVecchio']): ?>
        · dal <?= e(date('n/Y', strtotime((string)$coda['piuVecchio']))) ?>
        al <?= e(date('n/Y', strtotime((string)$coda['piuRecente']))) ?>
      <?php endif ?>
    </p>
  <?php endif ?>

  <?php if ($ultimo): ?>
    <h1 class="titoletto">ultimi giri</h1>
    <div class="bozza registro">
      <?php foreach ($ultimo as $l): ?>
        <div><strong><?= e($l['job']) ?></strong> · <?= e(quandoIt($l['finito_il'])) ?>
          · <?= e((string)$l['esito']) ?>
          <?= $l['messaggio'] ? '· ' . e(mb_substr((string)$l['messaggio'], 0, 90)) : '' ?></div>
      <?php endforeach ?>
    </div>
  <?php endif ?>

</div>

<script>
// "Seleziona tutti" e il contatore di quante righe sono selezionate: con
// trenta righe per pagina, sapere quante ne stai per pubblicare prima di
// premere il pulsante evita il tipo di errore che non si annulla.
(function () {
  var tutti = document.getElementById('tutti');
  var conta = document.getElementById('conta-sel');
  if (!tutti || !conta) { return; }
  var caselle = document.querySelectorAll('input[name="ids[]"]');

  function aggiorna() {
    var n = 0;
    caselle.forEach(function (c) { if (c.checked) { n++; } });
    conta.textContent = n;
    tutti.checked = n === caselle.length && n > 0;
    tutti.indeterminate = n > 0 && n < caselle.length;
  }
  tutti.addEventListener('change', function () {
    caselle.forEach(function (c) { c.checked = tutti.checked; });
    aggiorna();
  });
  caselle.forEach(function (c) { c.addEventListener('change', aggiorna); });
})();
</script>
