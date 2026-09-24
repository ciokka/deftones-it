-- =====================================================================
--  La tabella delle revisioni — le riletture di «migliora con IA».
--
--  Non tocca niente di esistente, e rieseguirlo non fa danni: la
--  tabella si crea solo se non c'è.
--
--  --- Da eseguire una volta sola, in phpMyAdmin, PRIMA che arrivi il
--  codice: senza la tabella la pagina di modifica di un articolo non si
--  apre più, perché elenca le riletture già chieste. ---
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- REVISIONI — le riletture dell'IA, in attesa del tuo giudizio.
--
--     Una revisione non tocca l'articolo: lo affianca. Il modello
--     rilegge un pezzo già scritto, cerca sul web cosa è successo dopo,
--     e propone una versione più approfondita — che resta qui finché
--     non la guardi. È l'unica forma onesta per un'IA che riscrive
--     qualcosa di già pubblicato: proporre, non sostituire.
--
--     Le colonne prima_* sono la fotografia dell'articolo com'era nel
--     momento in cui la revisione è partita. Servono a due cose: a
--     mostrare il confronto, e ad accorgersi se nel frattempo l'hai
--     modificato a mano — nel qual caso applicare la proposta
--     cancellerebbe il tuo lavoro senza dirtelo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS df_revisioni (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  articolo_id    BIGINT UNSIGNED NOT NULL,
  stato          ENUM('attesa','lavorazione','pronta','applicata','scartata','errore')
                 NOT NULL DEFAULT 'attesa',
  indicazioni    VARCHAR(500) NULL COMMENT 'cosa chiedi di approfondire, se hai un''idea precisa',

  -- com'era prima: la fotografia su cui il modello ha lavorato
  prima_titolo   VARCHAR(300) NULL,
  prima_sommario TEXT         NULL,
  prima_corpo    MEDIUMTEXT   NULL,
  prima_tag      JSON         NULL,

  -- come la propone il modello
  titolo_it      VARCHAR(300) NULL,
  sommario_it    TEXT         NULL,
  corpo_it       MEDIUMTEXT   NULL,
  tag            JSON         NULL,
  motivazione    TEXT         NULL COMMENT 'cosa ha cambiato e perché: si legge prima di applicare',
  fonti          JSON         NULL COMMENT 'pagine consultate. Vuoto = non si applica',

  nota           VARCHAR(500) NULL,
  modello        VARCHAR(50)  NULL,
  token_in       INT UNSIGNED NOT NULL DEFAULT 0,
  token_out      INT UNSIGNED NOT NULL DEFAULT 0,
  creato_il      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  elaborato_il   DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_rev_art (articolo_id, stato),
  KEY idx_rev_stato (stato, creato_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
