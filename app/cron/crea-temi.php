<?php
/**
 * crea-temi.php — raggruppa l'archivio in raccolte tematiche.
 *
 *   php crea-temi.php --prova    propone i temi e si ferma lì
 *   php crea-temi.php            propone, assegna gli articoli, salva
 *
 * Due fasi, non una. Prima il modello guarda tutto l'archivio e propone
 * le raccolte; poi, con quell'elenco fisso davanti, assegna gli articoli
 * a gruppi. Chiedendo tutto insieme l'elenco dei temi cambierebbe a metà
 * strada, e articoli valutati all'inizio finirebbero in temi che alla
 * fine non esistono più.
 */
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/claude.php';

@set_time_limit(0);
const PER_GRUPPO = 30;

$soloProva = in_array('--prova', $argv ?? [], true);
$avvio = microtime(true);

$lock = prendiLock('temi');
if ($lock === false) { logline('Un altro giro è già in corso — esco.', 'temi'); exit(0); }
function tlog(string $m): void { logline($m, 'temi'); }

$pdo = db();
$tab = t('articles');
$tokIn = $tokOut = 0;

// Solo gli articoli sopravvissuti alla valutazione: bozze e pubblicati.
$articoli = $pdo->query(
    "SELECT id, titolo_it, motivo, rilevanza, DATE(pubblicato_il) AS data
       FROM `$tab`
      WHERE categoria = 'evergreen' AND stato IN ('draft','pubblicato')
      ORDER BY pubblicato_il"
)->fetchAll();

if (!$articoli) {
    tlog('Nessun articolo da raggruppare.');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}
tlog(sprintf('%s — %d articoli', $soloProva ? 'PROVA' : 'Creazione temi', count($articoli)));

// ============================================================ fase 1
$sistemaTemi = <<<'TXT'
Sei l'archivista di deftones.it, sito italiano di fan dei Deftones
attivo dal 2002. Ricevi l'elenco completo dell'archivio: vent'anni di
articoli, con data e una riga che dice di cosa parlano.

Il tuo compito è riconoscere le RACCOLTE che ci sono dentro. Non
categorie generiche come "interviste" o "concerti": storie che
l'archivio ha raccontato a puntate e che, lette in fila, sono un
documento — mentre sparse fra centinaia di articoli si perdono.

Il criterio è: qualcuno arriverebbe sul sito per leggere questa
raccolta? Se la risposta è no, non è un tema.

Regole:
- da 4 a 8 raccolte, non di più: pochi dossier veri battono venti
  contenitori mezzi vuoti
- ognuna deve poter contare almeno 6-8 articoli, a occhio
- il titolo è per un lettore, non per un archivista
- il sottotitolo dice in una riga di cosa si tratta e in che anni
- l'introduzione è di 3-5 frasi: spiega perché questa raccolta esiste e
  cosa ci trova chi legge. Scrivila in italiano corrente, sobria. Niente
  enfasi da comunicato.
- lo slug è minuscolo, con trattini, senza accenti

Molti articoli non apparterranno a nessuna raccolta, ed è giusto così:
le raccolte servono a far emergere il meglio, non a sistemare tutto.
TXT;

$schemaTemi = [
    'type' => 'object',
    'properties' => ['temi' => [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'slug'         => ['type' => 'string'],
                'titolo'       => ['type' => 'string'],
                'sottotitolo'  => ['type' => 'string'],
                'introduzione' => ['type' => 'string'],
            ],
            'required' => ['slug','titolo','sottotitolo','introduzione'],
            'additionalProperties' => false,
        ],
    ]],
    'required' => ['temi'],
    'additionalProperties' => false,
];

$elenco = '';
foreach ($articoli as $a) {
    $elenco .= sprintf("%s · %s — %s\n", $a['data'],
        mb_substr((string)$a['titolo_it'], 0, 90), mb_substr((string)$a['motivo'], 0, 70));
}

$r = claudeJson($sistemaTemi, "Ecco l'archivio completo.\n\n" . $elenco, $schemaTemi, 8000);
$tokIn += $r['in']; $tokOut += $r['out'];

if (!$r['ok'] || empty($r['dati']['temi'])) {
    allarme('proposta dei temi fallita: ' . ($r['errore'] ?? 'risposta non conforme'), 'temi');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(1);
}
$temi = $r['dati']['temi'];

tlog(sprintf('%d raccolte proposte:', count($temi)));
foreach ($temi as $t) {
    tlog(sprintf('  %-26s %s', $t['slug'], mb_substr($t['titolo'], 0, 46)));
    tlog(sprintf('  %-26s   %s', '', mb_substr($t['sottotitolo'], 0, 70)));
}

if ($soloProva) {
    tlog(sprintf('PROVA finita — %d token in, %d out, circa %.2f €',
        $tokIn, $tokOut, costoEuro($tokIn, $tokOut)));
    tlog('Nessuna scrittura. Togli --prova per assegnare gli articoli.');
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    exit(0);
}

// --- salvataggio delle raccolte -------------------------------------
$salvaTema = $pdo->prepare('INSERT INTO ' . t('temi') . '
      (slug, titolo, sottotitolo, introduzione, ordine)
    VALUES (?,?,?,?,?)
    ON DUPLICATE KEY UPDATE titolo = VALUES(titolo),
      sottotitolo = VALUES(sottotitolo), introduzione = VALUES(introduzione),
      ordine = VALUES(ordine)');
$idPerSlug = [];
foreach ($temi as $i => $t) {
    $salvaTema->execute([mb_substr($t['slug'], 0, 120), mb_substr($t['titolo'], 0, 200),
        mb_substr($t['sottotitolo'], 0, 300), $t['introduzione'], ($i + 1) * 10]);
    $q = $pdo->prepare('SELECT id FROM ' . t('temi') . ' WHERE slug = ?');
    $q->execute([$t['slug']]);
    $idPerSlug[$t['slug']] = (int)$q->fetchColumn();
}

// ============================================================ fase 2
$sistemaAssegna = "Assegni articoli dell'archivio di deftones.it alle raccolte esistenti.\n\n"
    . "Le raccolte disponibili sono queste, e non se ne creano altre:\n\n";
foreach ($temi as $t) {
    $sistemaAssegna .= "- {$t['slug']}: {$t['titolo']} — {$t['sottotitolo']}\n";
}
$sistemaAssegna .= <<<'TXT'

Per ogni articolo indica la raccolta a cui appartiene, oppure "nessuna".

Assegna solo quando l'articolo è davvero parte di quella storia. Un
articolo che sfiora l'argomento non ci va: una raccolta con dentro
materiale marginale vale meno di una raccolta corta.

Nel dubbio, "nessuna".
TXT;

$schemaAssegna = [
    'type' => 'object',
    'properties' => ['assegnazioni' => [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'id'   => ['type' => 'integer'],
                'tema' => ['type' => 'string', 'description' => 'slug della raccolta, oppure "nessuna"'],
            ],
            'required' => ['id','tema'],
            'additionalProperties' => false,
        ],
    ]],
    'required' => ['assegnazioni'],
    'additionalProperties' => false,
];

$assegna = $pdo->prepare("UPDATE `$tab` SET tema_id = ? WHERE id = ?");
$gruppi = array_chunk($articoli, PER_GRUPPO);
$conteggio = [];

foreach ($gruppi as $n => $gruppo) {
    $p = '';
    foreach ($gruppo as $a) {
        $p .= sprintf("id %d · %s — %s | %s\n", $a['id'], $a['data'],
            mb_substr((string)$a['titolo_it'], 0, 90), mb_substr((string)$a['motivo'], 0, 70));
    }
    $r = claudeJson($sistemaAssegna, "Assegna questi articoli.\n\n" . $p, $schemaAssegna, 4000);
    $tokIn += $r['in']; $tokOut += $r['out'];

    if (!$r['ok'] || !isset($r['dati']['assegnazioni'])) {
        allarme(sprintf('gruppo %d fallito: %s', $n + 1, $r['errore'] ?? 'risposta non conforme'), 'temi');
        continue;
    }
    foreach ($r['dati']['assegnazioni'] as $v) {
        $id = $idPerSlug[$v['tema']] ?? null;
        $assegna->execute([$id, (int)$v['id']]);
        if ($id) { $conteggio[$v['tema']] = ($conteggio[$v['tema']] ?? 0) + 1; }
    }
    tlog(sprintf('  gruppo %d/%d', $n + 1, count($gruppi)));
}

arsort($conteggio);
tlog('Articoli per raccolta:');
foreach ($conteggio as $slug => $q) { tlog(sprintf('  %-28s %3d', $slug, $q)); }
$senza = count($articoli) - array_sum($conteggio);
tlog(sprintf('  %-28s %3d', '(nessuna raccolta)', $senza));

$pdo->prepare('INSERT INTO ' . t('run_log') . '
    (job, finito_il, esito, item_elaborati, token_in, token_out, messaggio)
    VALUES (?, NOW(), ?, ?, ?, ?, ?)')
    ->execute(['temi', 'ok', array_sum($conteggio), $tokIn, $tokOut,
               sprintf('%d raccolte, %d articoli assegnati', count($temi), array_sum($conteggio))]);

tlog(sprintf('Fatto in %.0fs — %d token in, %d out, circa %.2f €',
    microtime(true) - $avvio, $tokIn, $tokOut, costoEuro($tokIn, $tokOut)));
tlog('Le raccolte nascono in bozza: nessuna è online.');
tlog(str_repeat('-', 60));

if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
