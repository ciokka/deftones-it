<?php
/**
 * config.php — copia questo file in config.php e compila i valori.
 * config.php NON deve mai finire in git né dentro public_html.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'TUO_DATABASE',
        'user' => 'TUO_UTENTE',
        'pass' => 'LA_TUA_PASSWORD',
        'prefix' => 'df_',
    ],

    // chiave API Anthropic — crea la chiave su console.anthropic.com
    'anthropic_key' => '',

    // Modello e impegno di ragionamento.
    // 'effort' regola quanto il modello ragiona prima di rispondere:
    // low = più economico e sbrigativo, medium = equilibrio, high = default.
    // Serve solo se la tua chiave è "identity-linked": in quel caso l'API
    // risponde 400 finché non dichiari il workspace. Lo trovi nella Console,
    // Settings → Workspaces, ed è nella forma wrkspc_...
    // Con una chiave normale lascialo vuoto.
    'workspace_id' => '',

    'modello' => 'claude-opus-5',
    'effort'  => 'medium',

    // Sotto questa rilevanza (0-100) l'evento non diventa un articolo:
    // gli item vengono archiviati senza spendere una chiamata per scriverli.
    // Alzala se il sito ti sembra rumoroso, abbassala se pubblica troppo poco.
    'soglia_rilevanza' => 40,

    // Tetti per giro, per non trovarti un conto a sorpresa se un feed impazzisce
    'max_item_per_giro'   => 60,
    'max_eventi_per_giro' => 8,

    // token per lanciare i cron via URL, se un giorno servisse
    'cron_token' => 'GENERA_UNA_STRINGA_A_CASO',

    // Se un item non contiene nessuna di queste parole viene scartato
    // senza mai arrivare al modello. È il filtro che tiene bassa la spesa.
    'keywords' => [
        'deftones', 'chino moreno', 'stephen carpenter', 'abe cunningham',
        'frank delgado', 'sergio vega', 'chi cheng', 'crosses', 'palms band',
    ],

    // Quanti giorni indietro guardare per considerare un titolo un doppione
    'dedup_giorni' => 7,

    // Un articolo più vecchio di così non è una notizia: entra nell'archivio
    // ma non nella coda di lavorazione. Google News restituisce anni di
    // arretrato, questa riga da sola toglie di mezzo circa il 90%.
    'max_eta_giorni' => 14,


    // --- avvisi via email ---------------------------------------------
    // Dove arriva il riepilogo giornaliero.
    'email_avvisi' => 'deftones.it@gmail.com',
    // Da quale indirizzo parte. DEVE essere del dominio, altrimenti i
    // filtri antispam del destinatario lo scartano: un server che scrive
    // "da" un indirizzo @me.com non è autorizzato a farlo.
    'email_mittente' => 'sito@deftones.it',

    // --- sito ---------------------------------------------------------
    'site_name' => 'deftones.it',
    'site_url'  => 'https://www.deftones.it',
    // Sottocartella in cui gira il sito. Adesso '/v2' per non toccare il
    // vecchio WordPress; quando sposterai i file nella radice metti ''.
    'base_url'  => '/v2',
    // Per quanti secondi una pagina resta in cache. La cache viene comunque
    // svuotata a ogni pubblicazione, quindi puoi tenerla alta.
    'cache_ttl' => 900,

    'user_agent' => 'deftones.it/1.0 (aggregatore notizie fan; +https://www.deftones.it)',
    'http_timeout' => 15,
];
