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
                'grezzo' => $dati, 'in' => $in, 'out' => $out, 'errore' => null];
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

/**
 * Una chiamata in cui il modello cerca da sé sul web.
 *
 * Serve per i contenuti che devono durare: su una scheda che resta
 * anni, la memoria del modello non basta — una data sbagliata resta lì
 * e la copia qualcun altro. Con la ricerca attiva scrive da pagine che
 * ha davvero letto, e ne riportiamo gli indirizzi.
 *
 * @return array{ok:bool, testo:string, fonti:array, in:int, out:int, errore:?string}
 */
function claudeConRicerca(string $sistema, string $prompt,
                          int $maxRicerche = 8, int $maxRiprese = 4): array
{
    $messaggi = [['role' => 'user', 'content' => $prompt]];
    $testo = '';
    $fonti = [];
    $in = $out = 0;
    $letti = $scritti = 0;      // token serviti dalla cache, e scritti in cache

    for ($ripresa = 0; $ripresa <= $maxRiprese; $ripresa++) {
        $r = claude([
            'model'      => cfg('modello') ?: 'claude-opus-5',
            'max_tokens' => 16000,
            'system'     => $sistema,
            'messages'   => $messaggi,
            'tools'      => [[
                'type'     => 'web_search_20260209',
                'name'     => 'web_search',
                'max_uses' => $maxRicerche,
            ]],
            // Quando il giro si ferma per riprendere fiato rimandiamo
            // indietro tutta la conversazione, pagine lette comprese.
            // Senza cache quel materiale si paga a ogni ripresa: con 56
            // ricerche è il grosso del conto. La cache lo fa pagare un
            // decimo dalla seconda volta in poi.
            'cache_control' => ['type' => 'ephemeral'],
            'output_config' => ['effort' => cfg('effort') ?: 'medium'],
        ]);
        $in += $r['in']; $out += $r['out'];
        $u = $r['grezzo']['usage'] ?? [];
        $letti += (int)($u['cache_read_input_tokens'] ?? 0);
        $scritti += (int)($u['cache_creation_input_tokens'] ?? 0);

        // claude() considera un errore la risposta senza blocchi di testo,
        // ma qui è normale: un giro può contenere solo ricerche.
        $grezzo = $r['grezzo'] ?? null;
        if (!$grezzo) {
            return ['ok' => false, 'testo' => '', 'fonti' => [],
                    'in' => $in, 'out' => $out, 'errore' => $r['errore']];
        }

        foreach ($grezzo['content'] ?? [] as $b) {
            if (($b['type'] ?? '') === 'text') {
                $testo .= $b['text'];
            } elseif (($b['type'] ?? '') === 'web_search_tool_result') {
                // In caso di errore il contenuto è un oggetto, non una lista:
                // va distinto prima di scorrerlo.
                $c = $b['content'] ?? [];
                if (is_array($c) && array_is_list($c)) {
                    foreach ($c as $ris) {
                        if (!empty($ris['url'])) {
                            $fonti[$ris['url']] = $ris['title'] ?? $ris['url'];
                        }
                    }
                }
            }
        }

        // Il giro dei server tool si ferma a dieci passaggi: si riprende
        // rimandando indietro i messaggi così come sono, senza aggiungere
        // niente — il server riconosce la ricerca in coda e prosegue.
        if (($grezzo['stop_reason'] ?? '') !== 'pause_turn') {
            return ['ok' => true, 'testo' => $testo, 'fonti' => $fonti,
                    'in' => $in, 'out' => $out,
                    'cache_letti' => $letti, 'cache_scritti' => $scritti,
                    'errore' => null];
        }
        $messaggi[] = ['role' => 'assistant', 'content' => $grezzo['content']];
    }

    return ['ok' => $testo !== '', 'testo' => $testo, 'fonti' => $fonti,
            'in' => $in, 'out' => $out,
            'errore' => $testo === '' ? 'esaurite le riprese senza risposta' : null];
}
