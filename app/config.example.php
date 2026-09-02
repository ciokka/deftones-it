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
    // Sottocartella in cui gira il sito. Vuoto: il sito sta nella radice.
    // Serviva '/v2' finché convivevamo con il vecchio WordPress.
    'base_url'  => '',
    // Per quanti secondi una pagina resta in cache. La cache viene comunque
    // svuotata a ogni pubblicazione, quindi puoi tenerla alta.
    'cache_ttl' => 900,

    // Dove stanno i file caricati, sul disco. Ci finiscono le immagini
    // del vecchio WordPress e le copertine scaricate. È un percorso di
    // filesystem, non un indirizzo: gli articoli le cercano su /media/,
    // che è assoluto e non passa da base_url.
    'media_dir' => '/home/bpdefton/public_html/media',

    // Se accettare fotografie con licenza "non commerciale" (NC).
    //
    // true perché il sito non è commerciale e non ha intenzione di
    // diventarlo. Se un giorno ci comparisse un banner, un link
    // affiliato o una raccolta fondi, questa riga va rimessa a false —
    // e poi vanno scartate dal pannello le foto già entrate:
    //
    //   SELECT id, licenza, autore FROM df_immagini
    //    WHERE licenza REGEXP '(^|[^a-z])nc([^a-z]|$)';
    //
    // Le ND restano escluse in ogni caso, e non per prudenza: le
    // copertine si ritagliano a 16:9, e un ritaglio è un'opera derivata.
    'foto_non_commerciali' => true,

    // Credenziali di Openverse, gratuite e senza abbonamenti.
    //
    // Senza, la quota è di duecento richieste al giorno contate per
    // indirizzo IP — e su un hosting condiviso quell'indirizzo è lo
    // stesso di centinaia di altri siti, quindi può risultare esaurita
    // da gente che non sa di averla usata. Peggio: quando è esaurita la
    // richiesta non fallisce, resta appesa.
    //
    // Si ottengono in due minuti:
    //   curl -X POST https://api.openverse.org/v1/auth_tokens/register/ \
    //     -H 'Content-Type: application/json' \
    //     -d '{"name":"deftones.it","description":"sito di fan italiano",
    //          "email":"deftones.it@gmail.com"}'
    // Rispondono con client_id e client_secret, e mandano una email da
    // confermare: finché non la confermi le credenziali non funzionano.
    'openverse_id'     => '',
    'openverse_secret' => '',

    'user_agent' => 'deftones.it/1.0 (aggregatore notizie fan; +https://www.deftones.it)',
    'http_timeout' => 15,
];
