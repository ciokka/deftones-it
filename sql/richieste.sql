-- =====================================================================
--  Richieste di articolo
--
--  Un posto dove chiedere "scrivimi un pezzo su X". Il modello cerca le
--  fonti da sé e scrive: su un contenuto che resta anni la sua memoria
--  non basta, servono pagine davvero lette e citate.
-- =====================================================================

CREATE TABLE IF NOT EXISTS df_richieste (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  richiesta     VARCHAR(500) NOT NULL COMMENT 'di cosa deve parlare',
  indicazioni   TEXT         NULL     COMMENT 'taglio, lunghezza, cosa evitare',
  stato         ENUM('attesa','lavorazione','fatto','errore') NOT NULL DEFAULT 'attesa',
  articolo_id   BIGINT UNSIGNED NULL  COMMENT 'la bozza prodotta',
  fonti         JSON         NULL     COMMENT 'pagine consultate dal modello',
  nota          VARCHAR(500) NULL     COMMENT 'errore, se è andata male',
  token_in      INT UNSIGNED NOT NULL DEFAULT 0,
  token_out     INT UNSIGNED NOT NULL DEFAULT 0,
  creato_il     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  elaborato_il  DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_ric_stato (stato, creato_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT stato, COUNT(*) AS quante FROM df_richieste GROUP BY stato;
