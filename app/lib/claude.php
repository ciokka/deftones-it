<?php
/**
 * claude.php — client minimale per l'API Anthropic.
 *
 * Normalmente si userebbe l'SDK PHP ufficiale, ma su questo hosting
 * Composer non può girare (proc_open è disabilitato), quindi parliamo
 * direttamente con l'endpoint /v1/messages via cURL. È un endpoint solo.
 */
declare(strict_types=1);

const CLAUDE_URL     = 'https://api.anthropic.com/v1/messages';
const CLAUDE_VERSION = '2023-06-01';

/**
 * Una chiamata al modello, con retry su 429 e 5xx.
 *
 * @param array $corpo  il payload JSON (model, max_tokens, messages, ...)
 * @return array{ok:bool, testo:?string, dati:?array, in:int, out:int, errore:?string}
 */
function claude(array $corpo, int $tentativi = 3): array
{
    $chiave = (string)cfg('anthropic_key');
    if ($chiave === '') {
        return ['ok' => false, 'testo' => null, 'dati' => null,
                'in' => 0, 'out' => 0, 'errore' => 'anthropic_key non impostata in config.php'];
    }

    $intestazioni = [
        'content-type: application/json',
        'x-api-key: ' . $chiave,
        'anthropic-version: ' . CLAUDE_VERSION,
    ];
    // Le chiavi "identity-linked" (legate al tuo utente invece che a un
    // workspace) devono dichiarare in quale workspace opera la richiesta,
    // altrimenti l'API risponde 400. Le chiavi normali ignorano l'header,
    // quindi lo mandiamo solo se configurato.
    $workspace = (string)(cfg('workspace_id') ?? '');
    if ($workspace !== '') {
        $intestazioni[] = 'anthropic-workspace-id: ' . $workspace;
    }

    $payload = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ultimoErrore = null;

    for ($n = 1; $n <= $tentativi; $n++) {
        $ch = curl_init(CLAUDE_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,   // le risposte con ragionamento possono essere lente
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => $intestazioni,
        ]);
        $risposta = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errCurl = curl_error($ch);

        // errore di rete: riprova
        if ($risposta === false || $http === 0) {
            $ultimoErrore = 'rete: ' . ($errCurl ?: 'sconosciuto');
            if ($n < $tentativi) { sleep(2 ** $n); continue; }
            break;
        }

        $dati = json_decode((string)$risposta, true);

        // 429 e 5xx sono temporanei: aspetta e riprova
        if ($http === 429 || $http >= 500) {
            $attesa = 2 ** $n;
            $ultimoErrore = "HTTP $http: " . mb_substr((string)($dati['error']['message'] ?? $risposta), 0, 200);
            if ($n < $tentativi) { sleep($attesa); continue; }
            break;
        }

        // 4xx: è colpa nostra, non ha senso riprovare
        if ($http !== 200 || !is_array($dati)) {
            return ['ok' => false, 'testo' => null, 'dati' => null, 'in' => 0, 'out' => 0,
                    'errore' => "HTTP $http: " . mb_substr((string)($dati['error']['message'] ?? $risposta), 0, 300)];
        }

        $in  = (int)($dati['usage']['input_tokens'] ?? 0);
        $out = (int)($dati['usage']['output_tokens'] ?? 0);

        if (($dati['stop_reason'] ?? '') === 'refusal') {
            return ['ok' => false, 'testo' => null, 'dati' => null, 'in' => $in, 'out' => $out,
                    'errore' => 'il modello ha rifiutato la richiesta ('
                                . ($dati['stop_details']['category'] ?? 'senza categoria') . ')'];
        }

        // con il ragionamento attivo i blocchi 'thinking' vengono prima:
        // prendiamo il primo blocco di tipo 'text'
        $testo = null;
        foreach ($dati['content'] ?? [] as $blocco) {
            if (($blocco['type'] ?? '') === 'text') { $testo = $blocco['text']; break; }
        }
        if ($testo === null) {
            return ['ok' => false, 'testo' => null, 'dati' => null, 'in' => $in, 'out' => $out,
                    'errore' => 'risposta senza blocchi di testo'];
        }

        return ['ok' => true, 'testo' => $testo, 'dati' => json_decode($testo, true),
                'in' => $in, 'out' => $out, 'errore' => null];
    }

    return ['ok' => false, 'testo' => null, 'dati' => null, 'in' => 0, 'out' => 0,
            'errore' => $ultimoErrore ?? 'esauriti i tentativi'];
}

/** Chiamata che deve restituire JSON conforme a uno schema. */
function claudeJson(string $system, string $prompt, array $schema, int $maxTokens = 8000): array
{
    return claude([
        'model'      => cfg('modello') ?: 'claude-opus-5',
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
        'output_config' => [
            'effort' => cfg('effort') ?: 'medium',
            'format' => ['type' => 'json_schema', 'schema' => $schema],
        ],
    ]);
}

