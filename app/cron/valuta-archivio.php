<?php
/**
 * valuta-archivio.php — fa valutare l'archivio 2002-2021 al modello.
 *
 *   php valuta-archivio.php --prova    un solo gruppo, non scrive nulla
 *   php valuta-archivio.php            valuta tutte le bozze evergreen
 *
 * Perché non basta contare i caratteri: un articolo di 400 caratteri può
 * essere una notizia densa, e uno di 1500 pieno di tag può non dire
 * niente. E soprattutto tutte le bozze importate hanno rilevanza 50,
 * quindi oggi non c'è modo di sapere quali valga la pena pubblicare.
 *
 * Gli articoli vengono valutati a gruppi: una chiamata per venticinque,
 * invece di una ciascuno. Costa una frazione e il modello vede il
 * contesto degli altri, il che rende i punteggi confrontabili fra loro.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/claude.php';

@set_time_limit(0);
const PER_GRUPPO = 25;      // articoli per chiamata
const MAX_TESTO  = 900;     // caratteri di testo inviati per articolo

$soloProva = in_array('--prova', $argv ?? [], true);
$avvio = microtime(true);

$lock = prendiLock('valuta');
if ($lock === false) { logline('Un altro giro è già in corso — esco.', 'valuta'); exit(0); }
function vlog(string $m): void { logline($m, 'valuta'); }

$pdo = db();
$tab = t('articles');

// --- colonna per il motivo, se non c'è già ---------------------------
$q = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                       AND COLUMN_NAME = ? LIMIT 1');
$q->execute([$tab, 'motivo']);
if ($q->fetchColumn() === false && !$soloProva) {
    $pdo->exec("ALTER TABLE `$tab` ADD COLUMN motivo VARCHAR(300) NULL
                COMMENT 'perché il modello lo ha tenuto o scartato'");
    vlog('  aggiunta la colonna motivo');
}

// ---------------------------------------------------------------- coda
$articoli = $pdo->query(
    "SELECT id, titolo_it, sommario_it, corpo_it, pubblicato_il
       FROM `$tab`
      WHERE categoria = 'evergreen' AND stato = 'draft'
      ORDER BY pubblicato_il DESC"
)->fetchAll();

if (!$articoli) {
    vlog('Nessuna bozza da valutare.');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}

$gruppi = array_chunk($articoli, PER_GRUPPO);
if ($soloProva) { $gruppi = [array_slice($articoli, 0, PER_GRUPPO)]; }

vlog(sprintf('%s — %d articoli in %d gruppi da %d',
    $soloProva ? 'PROVA (nessuna scrittura)' : 'Valutazione',
    $soloProva ? count($gruppi[0]) : count($articoli), count($gruppi), PER_GRUPPO));

// ------------------------------------------------------------ istruzioni
$sistema = <<<'TXT'
Sei l'archivista di deftones.it, sito italiano di fan dei Deftones
attivo dal 2002. Ricevi articoli del vecchio archivio, dal 2002 al 2021,
e devi decidere quali meritano di tornare online oggi.

Il sito sta rinascendo. L'archivio è il suo patrimonio, ma contiene di
tutto: recensioni e interviste scritte con cura, e accanto centinaia di
avvisi che avevano senso solo nel loro momento.

SCARTA:
- avvisi legati a un momento passato che oggi non dicono nulla:
  "stasera in diretta su MTV", "biglietti in vendita da domani",
  "votate i Deftones al sondaggio", "il sito è in manutenzione"
- concorsi, sondaggi e iniziative scadute
- post che sono solo un link a un'altra pagina, senza contenuto proprio
- annunci di aggiornamenti del sito stesso o del forum
- testi che risultano tronchi o incomprensibili senza il contesto di allora

TIENI:
- interviste, recensioni, resoconti di concerti
- notizie su album, formazione, tour, side project
- tutto ciò che riguarda Chi Cheng, la sua malattia e la sua morte
- ricorrenze, retrospettive, approfondimenti sulla band
- qualunque cosa contenga fatti che valgono ancora

Assegna anche una rilevanza 0-100 per un fan italiano che arriva sul
sito OGGI, non per il lettore del 2007:
  85-100  interviste importanti, pezzi su Chi Cheng, storia della band
  60-84   recensioni, resoconti di concerti, notizie su album e tour
  35-59   notizie minori ma ancora leggibili
  1-34    materiale marginale
  0       da scartare

Il motivo deve stare in poche parole ed essere concreto: "avviso per una
diretta del 2009" è utile, "poco rilevante" no.

Sii severo. Seicento articoli mediocri seppelliscono i trenta che valgono.
TXT;

$schema = [
    'type' => 'object',
    'properties' => ['valutazioni' => [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'id'        => ['type' => 'integer'],
                'verdetto'  => ['type' => 'string', 'enum' => ['tieni', 'scarta']],
                'rilevanza' => ['type' => 'integer', 'description' => 'da 0 a 100'],
                'motivo'    => ['type' => 'string', 'description' => 'poche parole, concrete'],
            ],
            'required' => ['id', 'verdetto', 'rilevanza', 'motivo'],
            'additionalProperties' => false,
        ],
    ]],
    'required' => ['valutazioni'],
    'additionalProperties' => false,
];

// ---------------------------------------------------------------- giro
$aggiorna = $pdo->prepare("UPDATE `$tab`
                              SET stato = ?, rilevanza = ?, motivo = ?
                            WHERE id = ? AND stato = 'draft'");
$tenuti = $scartati = $tokIn = $tokOut = 0;

foreach ($gruppi as $n => $gruppo) {
    $titoli = array_column($gruppo, 'titolo_it', 'id');
    $prompt = '';
    foreach ($gruppo as $a) {
        $testo = trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags((string)($a['corpo_it'] ?: $a['sommario_it'])),
                               ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $prompt .= sprintf("--- id %d · %s\nTITOLO: %s\nTESTO: %s\n\n",
            $a['id'], substr((string)$a['pubblicato_il'], 0, 10),
            $a['titolo_it'], mb_substr($testo, 0, MAX_TESTO));
    }

    $r = claudeJson($sistema, "Valuta questi articoli dell'archivio.\n\n" . $prompt,
                    $schema, 8000);
    $tokIn += $r['in']; $tokOut += $r['out'];

    if (!$r['ok'] || !isset($r['dati']['valutazioni'])) {
        allarme(sprintf('gruppo %d fallito: %s', $n + 1, $r['errore'] ?? 'risposta non conforme'), 'valuta');
        continue;
    }

    foreach ($r['dati']['valutazioni'] as $v) {
        $stato = $v['verdetto'] === 'scarta' ? 'scartato' : 'draft';
        if ($stato === 'scartato') { $scartati++; } else { $tenuti++; }
        if (!$soloProva) {
            $aggiorna->execute([$stato, max(0, min(100, (int)$v['rilevanza'])),
                                mb_substr((string)$v['motivo'], 0, 300), (int)$v['id']]);
        }
        if ($soloProva) {
            vlog(sprintf('  %-7s %3d  %-50s  %s',
                $v['verdetto'], (int)$v['rilevanza'],
                mb_substr((string)($titoli[$v['id']] ?? '?'), 0, 50),
                mb_substr((string)$v['motivo'], 0, 58)));
        }
    }
    if (!$soloProva) {
        vlog(sprintf('  gruppo %d/%d — %d tenuti, %d scartati finora',
            $n + 1, count($gruppi), $tenuti, $scartati));
    }
}

$costo = costoEuro($tokIn, $tokOut);
if (!$soloProva) {
    $pdo->prepare('INSERT INTO ' . t('run_log') . '
        (job, finito_il, esito, item_elaborati, token_in, token_out, messaggio)
        VALUES (?, NOW(), ?, ?, ?, ?, ?)')
        ->execute(['valuta', 'ok', $tenuti + $scartati, $tokIn, $tokOut,
                   sprintf('%d tenuti, %d scartati', $tenuti, $scartati)]);
}

vlog(sprintf('Fatto in %.0fs — %d tenuti, %d scartati · %d token in, %d out, circa %.2f €',
    microtime(true) - $avvio, $tenuti, $scartati, $tokIn, $tokOut, $costo));
if ($soloProva) { vlog('PROVA: nessuna scrittura. Togli --prova per applicare.'); }
vlog(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
