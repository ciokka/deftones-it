<?php
/**
 * Le domande con cui si vanno a prendere le fotografie.
 *
 * Due elenchi e non uno, perché i due archivi non si interrogano allo
 * stesso modo: a Openverse si fa una domanda a parole, a Commons si dice
 * il nome esatto di una categoria. Metterli nella stessa tabella
 * sembrerebbe più ordinato e farebbe scrivere categorie inesistenti.
 */
$blocchi = [
    'openverse' => [
        'titolo'   => 'openverse — le parole',
        'spiega'   => 'Openverse indicizza Flickr e altri archivi liberi, e cerca '
                    . 'nel titolo, nella descrizione e nei tag. Per le persone ci '
                    . 'vuole comunque "deftones": "chino" da solo riporta indietro '
                    . 'mezzo mondo. Una domanda su una situazione — il palco, la '
                    . 'folla, un festival, un anno — porta foto che "deftones" e '
                    . 'basta non porta mai.',
        'esempio'  => 'deftones knotfest 2026',
        'lavoro'   => 'raccogli-altre',
        'etichetta'=> 'raccogli da openverse',
    ],
    'commons' => [
        'titolo'   => 'wikimedia commons — le categorie',
        'spiega'   => 'Qui non è una ricerca a parole: o la categoria esiste con '
                    . 'questo nome esatto, o non torna niente. Il prefisso '
                    . '"Category:" non serve, e se lo incolli lo tolgo io. Le '
                    . 'categorie di soggetto "band" vengono esplorate anche nelle '
                    . 'sottocategorie — i singoli concerti — ed è lì che stanno le '
                    . 'foto buone.',
        'esempio'  => 'Deftones concerts',
        'lavoro'   => 'raccogli',
        'etichetta'=> 'raccogli da commons',
    ],
];
?>
<div class="pannello">

  <div class="pannello-testa">
    <h1>le parole che cerchiamo</h1>
    <div class="azioni">
      <a class="bottone bottone-tenue" href="<?= u('admin/foto') ?>">
        <?= icona('immagine') ?> il catalogo
      </a>
    </div>
  </div>

  <?php if ($messaggio): ?>
    <?= avviso($messaggio) ?>
  <?php endif ?>

  <p class="occhiello">
    Con che cosa si vanno a cercare le fotografie da mettere in cima agli
    articoli. Quello che aggiungi qui vale dalla raccolta successiva: le
    raccolte non partono da sole quando salvi, perché durano minuti e
    consumano la quota giornaliera degli archivi.
  </p>
  <p class="occhiello">
    <em>Trovate</em> e <em>nuove</em> sono dell'ultimo giro: una ricerca
    che non porta più niente di nuovo si sospende, e resta qui coi suoi
    numeri nel caso ci si ripensi. Riaggiungere una parola che c'è già
    non fa un doppione — ne cambia il soggetto e le pagine.
  </p>

  <?php if (!$tabella): ?>
    <div class="avvisoKo">
      <p>
        La tabella delle ricerche non c'è ancora: va eseguito una volta
        sola <strong>sql/ricerche.sql</strong> in phpMyAdmin. Fino ad
        allora le raccolte continuano a funzionare, ma con l'elenco
        scritto dentro il programma — quello che questa pagina serve
        proprio a non dover più toccare.
      </p>
    </div>
  <?php endif ?>

  <?php foreach ($blocchi as $fonte => $b): ?>
    <?php $righe = array_values(array_filter($ricerche, fn($r) => $r['fonte'] === $fonte)); ?>

    <h2 class="sezione-ricerche"><?= e($b['titolo']) ?>
      <span class="conta"><?= count($righe) ?></span></h2>

    <p class="occhiello"><?= e($b['spiega']) ?></p>

    <?php if ($righe): ?>
      <table class="tabella tabella-ricerche">
        <thead>
          <tr>
            <th>domanda</th>
            <th>soggetto</th>
            <?php if ($fonte === 'openverse'): ?><th class="c-num">pagine</th><?php endif ?>
            <th class="c-quando">ultimo giro</th>
            <?php /* Le due colonne che rispondono alla sola domanda che
                     conta quando le parole si aggiungono a volontà:
                     questa qui porta foto, o consuma e basta? */ ?>
            <th class="c-num">trovate</th>
            <th class="c-num">nuove</th>
            <th class="c-azioni"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($righe as $r): ?>
            <tr<?= (int)$r['attiva'] ? '' : ' class="sospesa"' ?>>
              <td>
                <strong><?= e($r['domanda']) ?></strong>
                <?php if (!(int)$r['attiva']): ?>
                  <span class="stato-punto stato-scartato">sospesa</span>
                <?php endif ?>
              </td>
              <td><?= e($r['soggetto']) ?></td>
              <?php /* Su schermo stretto l'intestazione della tabella
                       sparisce, e quattro numeri in fila senza un nome
                       sopra non si sa più che cosa contino. Le etichette
                       compaiono solo lì: dove c'è l'intestazione
                       sarebbero la stessa parola scritta due volte. */ ?>
              <?php if ($fonte === 'openverse'): ?>
                <td class="c-num"><span class="solo-stretto">pagine </span><?= (int)$r['pagine'] ?></td>
              <?php endif ?>
              <td class="c-quando"><?= $r['ultimo_giro'] ? e(dataBreve($r['ultimo_giro'])) : '—' ?></td>
              <td class="c-num"><span class="solo-stretto">trovate </span><?= $r['ultimo_giro'] ? (int)$r['viste'] : '—' ?></td>
              <td class="c-num"><span class="solo-stretto">nuove </span><?= $r['ultimo_giro'] ? (int)$r['nuove'] : '—' ?></td>
              <td class="c-azioni">
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="che" value="attiva">
                  <button type="submit" class="azione<?= (int)$r['attiva'] ? ' accesa' : '' ?>"
                          title="<?= (int)$r['attiva'] ? 'sospendi: resta scritta, non viene più chiesta'
                                                      : 'rimetti in uso' ?>">
                    <?= icona((int)$r['attiva'] ? 'pubblica' : 'ritira') ?>
                  </button>
                </form>
                <?php /* Eliminare non tocca il catalogo: le fotografie
                         che quella ricerca aveva portato sono buone o no
                         per conto loro.

                         La domanda di conferma non nomina la ricerca:
                         una parola con l'apostrofo dentro chiuderebbe la
                         stringa JavaScript, e il pulsante smetterebbe di
                         funzionare proprio per le ricerche scritte in
                         italiano. */ ?>
                <form method="post" style="display:inline"
                      onsubmit="return confirm('Tolgo questa ricerca? Le foto che ha portato restano in catalogo.')">
                  <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="che" value="elimina">
                  <button type="submit" class="azione" title="togli dall'elenco">
                    <?= icona('scarta') ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    <?php elseif ($tabella): ?>
      <p class="vuoto">Nessuna ricerca: questa raccolta non ha niente da chiedere.</p>
    <?php endif ?>

    <?php /* Il modulo sta fuori dalla riga del pulsante di raccolta:
             dentro, aprendolo, i campi si incolonnavano stretti e il
             pulsante restava a mezz'aria di fianco a loro. */ ?>
    <details class="caricamento">
      <summary><?= icona('nuovo') ?> aggiungi</summary>
      <form method="post" class="modulo-foto">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="che" value="nuova">
        <input type="hidden" name="fonte" value="<?= e($fonte) ?>">

        <label><?= $fonte === 'commons' ? 'nome della categoria' : 'che cosa cercare' ?>
          <input type="text" name="domanda" maxlength="120" required
                 placeholder="<?= e($b['esempio']) ?>">
        </label>
        <label>soggetto
          <?php /* Non è un'etichetta descrittiva: è a chi verranno
                   attribuite le foto che tornano, e quindi su quali
                   articoli finiranno. Una ricerca su Chino con
                   soggetto "band" mette il suo ritratto in cima a un
                   pezzo sul chitarrista. */ ?>
          <select name="soggetto">
            <?php foreach ($soggetti as $sg): ?>
              <option value="<?= e($sg) ?>"><?= e($sg) ?></option>
            <?php endforeach ?>
          </select>
        </label>
        <?php if ($fonte === 'openverse'): ?>
          <label>pagine di risultati
            <?php /* Venti risultati per pagina, sei secondi fra una e
                     l'altra: otto pagine sono quasi un minuto per una
                     domanda sola. Per le ricerche su una persona non
                     serve insistere — dopo la terza pagina Openverse
                     dà roba che c'entra sempre meno. */ ?>
            <input type="number" name="pagine" min="1" max="12" value="4">
          </label>
        <?php endif ?>

        <button type="submit" class="bottone bottone-acceso">
          <?= icona('salva') ?> aggiungi
        </button>
      </form>
    </details>

    <div class="barra-raccolte">
      <?php /* La raccolta parte staccata dalla pagina: dura troppo
               perché il browser la aspetti. Il resoconto arriva nel log
               in fondo, che si legge ricaricando. */ ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="che" value="lavoro">
        <input type="hidden" name="quale" value="<?= e($b['lavoro']) ?>">
        <button type="submit" class="bottone bottone-tenue">
          <?= icona('raccogli') ?> <?= e($b['etichetta']) ?>
        </button>
      </form>
    </div>
  <?php endforeach ?>

  <?php if ($log !== ''): ?>
    <details class="resoconto">
      <summary>resoconto dell'ultima raccolta</summary>
      <pre><?= e($log) ?></pre>
    </details>
  <?php endif ?>
</div>
