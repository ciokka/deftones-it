-- =====================================================================
--  Temi: raccolte tematiche dell'archivio
--
--  Vent'anni di articoli contengono storie intere raccontate a puntate
--  — la vicenda di Chi Cheng dal 2008 al 2015, l'album Eros — che come
--  articoli sparsi si perdono e come raccolta valgono.
-- =====================================================================

CREATE TABLE IF NOT EXISTS df_temi (
  id             SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug           VARCHAR(120)  NOT NULL,
  titolo         VARCHAR(200)  NOT NULL,
  sottotitolo    VARCHAR(300)  NULL,
  introduzione   TEXT          NULL COMMENT 'perché questa raccolta esiste',
  ordine         SMALLINT      NOT NULL DEFAULT 0,
  stato          ENUM('draft','pubblicato') NOT NULL DEFAULT 'draft',
  creato_il      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aggiornato_il  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_temi_slug (slug),
  KEY idx_temi_ordine (stato, ordine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Un articolo appartiene a un tema solo: quello principale. Le
-- appartenenze secondarie le coprono già i tag, che esistono.
-- MySQL non conosce ADD COLUMN IF NOT EXISTS: se la colonna c'è già,
-- questa riga darà errore e va semplicemente saltata.
ALTER TABLE df_articles
  ADD COLUMN tema_id SMALLINT UNSIGNED NULL COMMENT 'raccolta di appartenenza',
  ADD KEY idx_art_tema (tema_id, pubblicato_il);


-- ── verifica ─────────────────────────────────────────────────────────
SELECT t.ordine, t.slug, t.titolo, COUNT(a.id) AS articoli
FROM df_temi t LEFT JOIN df_articles a ON a.tema_id = t.id
GROUP BY t.id ORDER BY t.ordine;
