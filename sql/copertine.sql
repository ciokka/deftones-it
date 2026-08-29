-- =====================================================================
--  Copertine degli articoli
--
--  immagine_url esisteva già dallo schema iniziale, con un commento che
--  vale ancora: "solo immagini nostre o con licenza — mai foto delle
--  testate". Queste colonne sono quello che serve per rispettarlo sul
--  serio, cioè per poter dire di chi è ogni foto e a quali condizioni
--  la stiamo mostrando.
--
--  Una foto CC BY è libera *a condizione* di citare l'autore. Senza un
--  posto dove tenere quel nome, la condizione non è rispettabile e la
--  foto non è libera. Da qui immagine_autore e immagine_licenza.
--
--  Eseguire una volta sola, da phpMyAdmin. Le colonne che esistono già
--  vengono saltate: MySQL non conosce ADD COLUMN IF NOT EXISTS, quindi
--  si passa da information_schema.
-- =====================================================================

SET @db := DATABASE();
SET @tb := 'df_articles';

-- --- chi ha scattato la foto -----------------------------------------
SET @c := 'immagine_autore';
SET @s := (SELECT IF(COUNT(*) > 0, 'SELECT 1',
  'ALTER TABLE df_articles ADD COLUMN immagine_autore VARCHAR(200) NULL
     COMMENT "chi va citato — per le CC BY non è facoltativo" AFTER immagine_url')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tb AND COLUMN_NAME = @c);
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- --- a quali condizioni ----------------------------------------------
SET @c := 'immagine_licenza';
SET @s := (SELECT IF(COUNT(*) > 0, 'SELECT 1',
  'ALTER TABLE df_articles ADD COLUMN immagine_licenza VARCHAR(80) NULL
     COMMENT "CC BY 2.0, CC BY-SA 4.0, pubblico dominio…" AFTER immagine_autore')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tb AND COLUMN_NAME = @c);
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SET @c := 'immagine_licenza_url';
SET @s := (SELECT IF(COUNT(*) > 0, 'SELECT 1',
  'ALTER TABLE df_articles ADD COLUMN immagine_licenza_url VARCHAR(400) NULL
     AFTER immagine_licenza')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tb AND COLUMN_NAME = @c);
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- --- dov'è l'originale, per chi vuole risalire -----------------------
SET @c := 'immagine_fonte_url';
SET @s := (SELECT IF(COUNT(*) > 0, 'SELECT 1',
  'ALTER TABLE df_articles ADD COLUMN immagine_fonte_url VARCHAR(600) NULL
     COMMENT "la pagina del file su Commons, non il file" AFTER immagine_licenza_url')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tb AND COLUMN_NAME = @c);
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- --- da dove viene, e quando abbiamo cercato -------------------------
SET @c := 'immagine_origine';
SET @s := (SELECT IF(COUNT(*) > 0, 'SELECT 1',
  'ALTER TABLE df_articles ADD COLUMN immagine_origine
     ENUM("commons","disco","generata","manuale") NULL AFTER immagine_fonte_url')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tb AND COLUMN_NAME = @c);
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Serve a non ricercare all'infinito gli articoli per cui non si è
-- trovato niente, e a poter dire "riprova quelli vecchi di un mese".
SET @c := 'immagine_cercata_il';
SET @s := (SELECT IF(COUNT(*) > 0, 'SELECT 1',
  'ALTER TABLE df_articles ADD COLUMN immagine_cercata_il DATETIME NULL
     AFTER immagine_origine')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tb AND COLUMN_NAME = @c);
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Per scegliere le foto meno usate senza scandire tutta la tabella.
SET @c := 'idx_art_immagine';
SET @s := (SELECT IF(COUNT(*) > 0, 'SELECT 1',
  'CREATE INDEX idx_art_immagine ON df_articles (immagine_cercata_il, stato)')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tb AND INDEX_NAME = @c);
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

SELECT COLUMN_NAME, COLUMN_TYPE
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'df_articles'
   AND COLUMN_NAME LIKE 'immagine%'
 ORDER BY ORDINAL_POSITION;

-- =====================================================================
--  Il catalogo delle immagini
--
--  Commons non viene interrogato quando serve una copertina: viene
--  interrogato ogni tanto, e quello che trova finisce qui. Assegnare
--  una copertina diventa così una query locale, che non dipende dalla
--  rete, non ha limiti di frequenza e si può rifare quante volte vuoi.
--
--  In più il catalogo si può curare: una foto brutta o sbagliata la
--  marchi scartata e non viene più scelta, senza doverla cancellare e
--  senza che la prossima raccolta la riporti dentro.
-- =====================================================================

CREATE TABLE IF NOT EXISTS df_immagini (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  commons      VARCHAR(300) NOT NULL COMMENT 'titolo del file su Commons, senza il prefisso File:',
  url_file     VARCHAR(700) NOT NULL COMMENT 'la miniatura a 1200px, non l''originale da 20 MB',
  url_pagina   VARCHAR(600) NOT NULL COMMENT 'la pagina di descrizione, quella da linkare nel credito',
  autore       VARCHAR(200) NULL,
  licenza      VARCHAR(80)  NULL,
  licenza_url  VARCHAR(400) NULL,
  larghezza    SMALLINT UNSIGNED NULL,
  altezza      SMALLINT UNSIGNED NULL,
  soggetto     VARCHAR(40)  NOT NULL DEFAULT 'band'
               COMMENT 'band, chino, stephen, sergio… serve a scegliere la foto giusta',
  usata        SMALLINT UNSIGNED NOT NULL DEFAULT 0
               COMMENT 'quante volte è già stata assegnata: si preferiscono le meno usate',
  scartata     TINYINT(1)   NOT NULL DEFAULT 0
               COMMENT 'foto che non vuoi vedere: restano nel catalogo ma non vengono scelte',
  aggiunta_il  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_img_commons (commons),
  KEY idx_img_scelta (soggetto, scartata, usata)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
