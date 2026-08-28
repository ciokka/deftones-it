-- =====================================================================
--  deftones.it — schema database
--  MySQL 8.0.46 · InnoDB · utf8mb4
--
--  COME ESEGUIRLO
--    cPanel → phpMyAdmin → seleziona il database → tab "SQL" → incolla
--    (oppure: Importa → carica questo file)
--
--  DATABASE  bpdefton_base  (il piano ne consente uno solo, condiviso
--            con WordPress). Tutte le tabelle hanno prefisso df_ e non
--            toccano le wp_*. Quando il sito nuovo sarà online potrai
--            cancellare le wp_* e resterà tutto pulito.
--
--  NOTA      Ogni tabella dichiara utf8mb4 esplicitamente, quindi il
--            default latin1 del database non crea problemi.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+01:00';


-- ---------------------------------------------------------------------
-- 1. SOURCES — i feed da cui peschiamo
-- ---------------------------------------------------------------------
CREATE TABLE df_sources (
  id                  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome                VARCHAR(80)   NOT NULL,
  url_feed            VARCHAR(500)  NOT NULL,
  tipo                ENUM('rss','atom','json','api') NOT NULL DEFAULT 'rss',
  lingua              CHAR(2)       NOT NULL DEFAULT 'en',
  peso                TINYINT UNSIGNED NOT NULL DEFAULT 50
                      COMMENT 'affidabilità 0-100: a parità di notizia vince la fonte col peso più alto',
  filtra_keyword      TINYINT(1)    NOT NULL DEFAULT 1
                      COMMENT '0 = feed già monotematico, prendi tutto senza filtrare',
  attivo              TINYINT(1)    NOT NULL DEFAULT 1,
  etag                VARCHAR(255)  NULL COMMENT 'conditional GET: evita di riscaricare feed immutati',
  last_modified       VARCHAR(100)  NULL,
  ultimo_fetch        DATETIME      NULL,
  ultimo_esito        VARCHAR(255)  NULL,
  errori_consecutivi  SMALLINT UNSIGNED NOT NULL DEFAULT 0
                      COMMENT 'a 10 il feed si autodisattiva, così un dominio morto non rallenta il cron',
  PRIMARY KEY (id),
  UNIQUE KEY uq_sources_feed (url_feed(255)),
  KEY idx_sources_attivo (attivo, ultimo_fetch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. RAW_ITEMS — tutto ciò che entra, prima di qualunque elaborazione.
--    Non si cancella mai: è la memoria che impedisce di ri-processare
--    (e ri-pagare) la stessa notizia.
-- ---------------------------------------------------------------------
CREATE TABLE df_raw_items (
  id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  source_id      SMALLINT UNSIGNED NOT NULL,
  url            VARCHAR(1000)    NOT NULL,
  url_canonico   VARCHAR(1000)    NOT NULL
                 COMMENT 'senza utm_*, fbclid, ecc. — Google News redirige, va risolto',
  src_url_hash   CHAR(40)         NOT NULL
                 COMMENT 'sha1 dell URL come arriva dal feed — evita di risolvere due volte lo stesso redirect Google News',
  url_hash       CHAR(40)         NOT NULL COMMENT 'sha1(url_canonico) — dedup fra testate diverse',
  titolo         VARCHAR(500)     NOT NULL,
  titolo_hash    CHAR(40)         NOT NULL
                 COMMENT 'sha1 del titolo normalizzato — becca le 8 testate che ribattono la stessa notizia',
  estratto       MEDIUMTEXT       NULL,
  autore         VARCHAR(200)     NULL,
  pubblicato_il  DATETIME         NULL,
  visto_il       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  stato          ENUM('nuovo','scartato_keyword','duplicato','elaborato','errore')
                 NOT NULL DEFAULT 'nuovo',
  duplicato_di   BIGINT UNSIGNED  NULL,
  nota           TEXT             NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_raw_src (src_url_hash),
  KEY idx_raw_url (url_hash),
  KEY idx_raw_titolo (titolo_hash, visto_il),
  KEY idx_raw_stato (stato, visto_il),
  KEY idx_raw_source (source_id, visto_il),
  CONSTRAINT fk_raw_source FOREIGN KEY (source_id) REFERENCES df_sources(id) ON DELETE CASCADE,
  CONSTRAINT fk_raw_dup    FOREIGN KEY (duplicato_di) REFERENCES df_raw_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. ARTICLES — il contenuto pubblicabile in italiano.
--    Ospita sia le notizie generate dalla pipeline sia le pagine
--    evergreen scritte a mano (raw_item_id NULL, categoria 'evergreen').
-- ---------------------------------------------------------------------
CREATE TABLE df_articles (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  raw_item_id    BIGINT UNSIGNED NULL,
  slug           VARCHAR(200)    NOT NULL,
  titolo_it      VARCHAR(300)    NOT NULL,
  sommario_it    TEXT            NOT NULL COMMENT '80-120 parole riscritte — mai il testo della fonte',
  corpo_it       MEDIUMTEXT      NULL     COMMENT 'solo per le pagine evergreen',
  categoria      ENUM('news','tour','uscita','intervista','rumor','video','evergreen')
                 NOT NULL DEFAULT 'news',
  tag            JSON            NULL,
  rilevanza      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100 assegnato dal modello',
  attendibilita  ENUM('confermato','rumor','speculazione') NOT NULL DEFAULT 'confermato',
  fonte_nome     VARCHAR(120)    NULL,
  fonte_url      VARCHAR(1000)   NULL,
  immagine_url   VARCHAR(1000)   NULL COMMENT 'solo immagini nostre o con licenza — mai foto delle testate',
  stato          ENUM('draft','pubblicato','scartato') NOT NULL DEFAULT 'draft',
  modello        VARCHAR(50)     NULL COMMENT 'quale modello ha generato, per confrontare la qualità',
  uso_token      JSON            NULL COMMENT '{"in":1234,"out":456} — per sapere quanto spendi davvero',
  creato_il      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  pubblicato_il  DATETIME        NULL,
  aggiornato_il  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_art_slug (slug),
  KEY idx_art_pub (stato, pubblicato_il DESC),
  KEY idx_art_cat (categoria, stato, pubblicato_il DESC),
  KEY idx_art_draft (stato, rilevanza DESC),
  FULLTEXT KEY ft_art_ricerca (titolo_it, sommario_it),
  CONSTRAINT fk_art_raw FOREIGN KEY (raw_item_id) REFERENCES df_raw_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. ALBUMS — discografia. Contenuto evergreen, il vero motore SEO.
-- ---------------------------------------------------------------------
CREATE TABLE df_albums (
  id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(120)  NOT NULL,
  titolo          VARCHAR(200)  NOT NULL,
  tipo            ENUM('album','ep','live','raccolta','singolo','side-project')
                  NOT NULL DEFAULT 'album',
  anno            SMALLINT UNSIGNED NULL,
  data_uscita     DATE          NULL,
  etichetta       VARCHAR(120)  NULL,
  produttore      VARCHAR(200)  NULL,
  copertina       VARCHAR(300)  NULL,
  descrizione_it  MEDIUMTEXT    NULL,
  tracklist       JSON          NULL COMMENT '[{"n":1,"titolo":"...","durata":"4:12"}]',
  mbid            CHAR(36)      NULL COMMENT 'MusicBrainz release-group id',
  ordine          SMALLINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alb_slug (slug),
  KEY idx_alb_ordine (tipo, ordine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 5. SHOWS — concerti passati e futuri (Bandsintown + setlist.fm).
--    La vista sui soli concerti italiani è contenuto originale
--    che non esiste da nessun'altra parte in italiano.
-- ---------------------------------------------------------------------
CREATE TABLE df_shows (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  data_evento    DATE          NOT NULL,
  ora            TIME          NULL,
  venue          VARCHAR(200)  NULL,
  citta          VARCHAR(120)  NULL,
  paese          CHAR(2)       NULL COMMENT 'ISO 3166-1 alpha-2',
  festival       VARCHAR(200)  NULL,
  url_biglietti  VARCHAR(500)  NULL,
  setlist        JSON          NULL,
  note_it        TEXT          NULL,
  fonte          ENUM('bandsintown','setlistfm','manuale') NOT NULL DEFAULT 'bandsintown',
  fonte_id       VARCHAR(80)   NULL,
  aggiornato_il  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_show_fonte (fonte, fonte_id),
  KEY idx_show_data (data_evento DESC),
  KEY idx_show_paese (paese, data_evento DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 6. ADMIN_USERS — un utente, tu. password_hash() di PHP, mai in chiaro.
-- ---------------------------------------------------------------------
CREATE TABLE df_admin_users (
  id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username        VARCHAR(60)  NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  ultimo_accesso  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 7. RUN_LOG — cosa ha fatto ogni giro di cron e quanto è costato.
--    Serve a rispondere a "perché stanotte non è uscito niente?"
--    senza doverci mettere mano.
-- ---------------------------------------------------------------------
CREATE TABLE df_run_log (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job             VARCHAR(40)  NOT NULL COMMENT 'ingest | enrich | tour | cache',
  iniziato_il     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finito_il       DATETIME     NULL,
  esito           ENUM('ok','parziale','errore') NULL,
  item_nuovi      INT UNSIGNED NOT NULL DEFAULT 0,
  item_elaborati  INT UNSIGNED NOT NULL DEFAULT 0,
  token_in        INT UNSIGNED NOT NULL DEFAULT 0,
  token_out       INT UNSIGNED NOT NULL DEFAULT 0,
  messaggio       TEXT         NULL,
  PRIMARY KEY (id),
  KEY idx_log_job (job, iniziato_il DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
--  SEED — feed di partenza
--
--  attivo=1  → URL verificato dal test di diagnostica, funziona
--  attivo=0  → URL da verificare prima di accendere (aprilo nel browser:
--              se vedi XML è buono, se vedi un 404 correggilo)
-- =====================================================================
INSERT INTO df_sources (nome, url_feed, tipo, lingua, peso, filtra_keyword, attivo) VALUES
-- aggregatore generico: è il motore principale
('Google News · Deftones (IT)', 'https://news.google.com/rss/search?q=Deftones&hl=it&gl=IT&ceid=IT:it', 'rss', 'it', 60, 0, 1),
('Google News · Deftones (EN)', 'https://news.google.com/rss/search?q=Deftones&hl=en-US&gl=US&ceid=US:en', 'rss', 'en', 60, 0, 1),
('Google News · Chino Moreno',  'https://news.google.com/rss/search?q=%22Chino+Moreno%22&hl=en-US&gl=US&ceid=US:en', 'rss', 'en', 55, 0, 1),
('Google News · Crosses',       'https://news.google.com/rss/search?q=%22Crosses%22+Chino+Moreno&hl=en-US&gl=US&ceid=US:en', 'rss', 'en', 50, 0, 1),
-- testate verificate (HTTP 200 nel test)
('Blabbermouth',  'https://blabbermouth.net/feed', 'rss', 'en', 85, 1, 1),
('Loudwire',      'https://loudwire.com/feed/',    'rss', 'en', 75, 1, 1),
-- community: spesso anticipa le testate
('Reddit r/Deftones', 'https://www.reddit.com/r/Deftones/.rss', 'rss', 'en', 30, 1, 1),
-- testate verificate (HTTP 200 con item validi al 27/08/2026)
('Metal Injection', 'https://metalinjection.net/feed',     'rss', 'en', 70, 1, 1),
('Stereogum',       'https://www.stereogum.com/feed/',     'rss', 'en', 75, 1, 1),
('Consequence',     'https://consequence.net/feed/',       'rss', 'en', 75, 1, 1),
('BrooklynVegan',   'https://www.brooklynvegan.com/feed/', 'rss', 'en', 65, 1, 1),
('Revolver',        'https://www.revolvermag.com/rss.xml', 'rss', 'en', 70, 1, 1),
('NME',             'https://www.nme.com/news/music/feed', 'rss', 'en', 70, 1, 1),
-- Kerrang!: /feed risponde 404, l'URL giusto va cercato nel sorgente
-- della home (<link rel="alternate" type="application/rss+xml">)
('Kerrang!', 'https://www.kerrang.com/feed', 'rss', 'en', 70, 1, 0),
-- YouTube ufficiale: apri il canale, Visualizza sorgente, cerca "channelId",
-- incolla qui il valore che inizia per UC e metti attivo=1
('YouTube · Deftones', 'https://www.youtube.com/feeds/videos.xml?channel_id=CHANNEL_ID', 'atom', 'en', 90, 0, 0),
-- deftones.com non espone un feed RSS (404 su /feed/ e /news):
-- servirà uno scraper dedicato, lo aggiungiamo più avanti
('Deftones.com', 'https://deftones.com/feed/', 'rss', 'en', 100, 0, 0);
