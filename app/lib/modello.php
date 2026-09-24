<?php
/**
 * modello.php — quale modello scrive, e quanto costa.
 *
 * Prima il modello stava in config.php: cambiarlo voleva dire aprire il
 * file sul server, e un modello nuovo arrivava solo quando qualcuno se
 * ne accorgeva. Adesso si sceglie dal pannello (admin/modello) e sta
 * nel database, in df_impostazioni. config.php resta la riserva: vale
 * finché nel pannello non si è scelto niente, e quando il modello scelto
 * viene rifiutato dall'API.
 *
 * La scelta "automatico" vuol dire: l'Opus più recente. Una volta al
 * giorno si chiede all'API l'elenco dei modelli (GET /v1/models) e, se
 * è uscito un Opus nuovo, si passa a quello da soli. Opus e non il
 * modello più capace in assoluto perché quello costa il doppio: la
 * linea Opus è quella che il sito ha sempre usato.
 */
declare(strict_types=1);

const ANTHROPIC_MODELLI = 'https://api.anthropic.com/v1/models';
const MODELLO_RISERVA   = 'claude-opus-5';
const MODELLO_CONTROLLO = 86400;    // ogni quanto l'automatico guarda se c'è di nuovo

/**
 * Le intestazioni di ogni richiesta all'API: le usano sia i messaggi
 * sia l'elenco dei modelli.
 */
function anthropicIntestazioni(): array
{
    $h = [
        'content-type: application/json',
        'x-api-key: ' . (string)cfg('anthropic_key'),
        'anthropic-version: 2023-06-01',
    ];
    // Le chiavi "identity-linked" (legate al tuo utente invece che a un
    // workspace) devono dichiarare in quale workspace opera la richiesta,
    // altrimenti l'API risponde 400. Le chiavi normali ignorano l'header,
    // quindi lo mandiamo solo se configurato.
    $workspace = (string)(cfg('workspace_id') ?? '');
    if ($workspace !== '') { $h[] = 'anthropic-workspace-id: ' . $workspace; }
    return $h;
}

// ---------------------------------------------------------- impostazioni

/**
 * Un valore di df_impostazioni, o null se non c'è.
 *
 * La tabella nasce da sé alla prima scrittura: su un database che non
 * l'ha ancora, leggere risponde null e il sito va avanti con config.php
 * invece di fermarsi su un errore.
 */
function impostazione(string $chiave): ?string
{
    try {
        $q = db()->prepare('SELECT valore FROM ' . t('impostazioni') . ' WHERE chiave = ?');
        $q->execute([$chiave]);
        $v = $q->fetchColumn();
        return $v === false ? null : (string)$v;
    } catch (PDOException) {
        return null;
    }
}

function impostaValore(string $chiave, string $valore): void
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS ' . t('impostazioni') . ' (
                  chiave         VARCHAR(60) NOT NULL,
                  valore         TEXT        NOT NULL,
                  aggiornato_il  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (chiave)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->prepare('INSERT INTO ' . t('impostazioni') . ' (chiave, valore) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE valore = VALUES(valore)')
        ->execute([$chiave, $valore]);
}

// --------------------------------------------------------------- modello

/** Il modello di riserva: quello di config.php. */
function modelloRiserva(): string
{
    return (string)(cfg('modello') ?: MODELLO_RISERVA);
}

/** 'auto' oppure l'id di un modello. Vuoto = non si è mai scelto. */
function sceltaModello(): string
{
    return (string)(impostazione('modello') ?? '');
}

/**
 * Il modello con cui parlare adesso.
 *
 * Con la scelta automatica, se l'ultimo controllo ha più di un giorno
 * se ne fa uno nuovo. Se l'API non risponde si resta su quello di
 * prima: non avere notizie non è un motivo per cambiare.
 */
function modello(bool $rileggi = false): string
{
    // Si legge una volta per processo: un giro di cron non deve cambiare
    // modello a metà. Chi ha appena salvato una scelta chiede $rileggi.
    static $m = null;
    if ($m !== null && !$rileggi) { return $m; }

    $scelta = sceltaModello();
    if ($scelta === '') { return $m = modelloRiserva(); }
    if ($scelta !== 'auto') { return $m = $scelta; }

    $attuale   = impostazione('modello_auto') ?: modelloRiserva();
    $controllo = (int)(impostazione('modello_auto_controllo') ?? 0);
    if (time() - $controllo >= MODELLO_CONTROLLO) {
        $m = aggiornaModelloAuto($attuale);
        return $m;
    }
    return $m = $attuale;
}

/**
 * Chiede all'API l'Opus più recente e, se è cambiato, ci passa.
 * Il cambio finisce nel log e nel riepilogo per posta: un modello
 * nuovo scrive in modo diverso, e va saputo prima di leggere le bozze.
 */
function aggiornaModelloAuto(string $attuale): string
{
    // Il controllo si segna prima di farlo: se l'API non risponde si
    // riprova domani, invece di aspettarla a ogni pagina del pannello.
    impostaValore('modello_auto_controllo', (string)time());

    $elenco = elencoModelli(true);
    if ($elenco === null) { return $attuale; }   // API muta: niente cambi

    $opus = array_values(array_filter($elenco,
        fn($x) => str_starts_with($x['id'], 'claude-opus-')));
    if (!$opus) { return $attuale; }

    $nuovo = $opus[0]['id'];      // elencoModelli() li ordina dal più recente
    if ($nuovo !== $attuale) {
        impostaValore('modello_auto', $nuovo);
        impostaValore('modello_cambiato', json_encode(
            ['da' => $attuale, 'a' => $nuovo, 'il' => date('Y-m-d H:i:s')]));
        logline("Modello automatico: da $attuale a $nuovo", 'modello');
    }
    return $nuovo;
}

/**
 * I modelli che la chiave può usare, dal più recente, come
 * [['id' => ..., 'nome' => ..., 'uscito' => 'Y-m-d'], ...].
 *
 * L'elenco resta in df_impostazioni per un giorno: il pannello lo
 * mostra a ogni apertura, e non serve chiederlo ogni volta.
 *
 * @return ?array null se l'API non ha risposto e non c'è una copia
 */
function elencoModelli(bool $fresco = false): ?array
{
    $copia = json_decode((string)(impostazione('modelli_elenco') ?? ''), true);
    $eta   = time() - (int)(impostazione('modelli_elenco_il') ?? 0);
    if (!$fresco && is_array($copia) && $eta < MODELLO_CONTROLLO) { return $copia; }

    if ((string)cfg('anthropic_key') === '') { return is_array($copia) ? $copia : null; }

    $ch = curl_init(ANTHROPIC_MODELLI . '?limit=100');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => anthropicIntestazioni(),
    ]);
    $r = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $dati = $http === 200 ? json_decode((string)$r, true) : null;

    if (!is_array($dati['data'] ?? null)) {
        logline("Elenco dei modelli non disponibile (HTTP $http)", 'modello');
        return is_array($copia) ? $copia : null;
    }

    $elenco = [];
    foreach ($dati['data'] as $x) {
        if (empty($x['id'])) { continue; }
        $elenco[] = [
            'id'     => (string)$x['id'],
            'nome'   => (string)($x['display_name'] ?? $x['id']),
            'uscito' => substr((string)($x['created_at'] ?? ''), 0, 10),
        ];
    }
    usort($elenco, fn($a, $b) => strcmp($b['uscito'], $a['uscito']));

    impostaValore('modelli_elenco', json_encode($elenco, JSON_UNESCAPED_UNICODE));
    impostaValore('modelli_elenco_il', (string)time());
    return $elenco;
}

// ---------------------------------------------------------------- tariffe

/**
 * Dollari per milione di token, [ingresso, uscita].
 *
 * L'API dice quali modelli esistono ma non quanto costano, quindi le
 * tariffe stanno qui, per famiglia: il prefisso più lungo che combacia
 * vince. Un modello che non c'è ancora viene stimato come Opus 5, e il
 * pannello dei costi lo dice.
 */
const TARIFFE = [
    'claude-fable-'    => [10.00, 50.00],
    'claude-mythos-'   => [10.00, 50.00],
    'claude-opus-5-5'  => [ 4.00, 20.00],
    'claude-opus-'     => [ 5.00, 25.00],
    'claude-sonnet-5'  => [ 2.00, 10.00],
    'claude-sonnet-'   => [ 3.00, 15.00],
    'claude-haiku-'    => [ 1.00,  5.00],
];

/** @return array{0:float,1:float,2:bool} ingresso, uscita, e se la tariffa è nota */
function tariffe(?string $modello = null): array
{
    $modello ??= modello();
    $meglio = null;
    foreach (TARIFFE as $prefisso => $t) {
        if (str_starts_with($modello, $prefisso)
            && ($meglio === null || strlen($prefisso) > strlen($meglio))) {
            $meglio = $prefisso;
        }
    }
    return $meglio === null
        ? [5.00, 25.00, false]
        : [TARIFFE[$meglio][0], TARIFFE[$meglio][1], true];
}
