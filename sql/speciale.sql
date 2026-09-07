-- =====================================================================
--  L'etichetta "special"
--
--  Il bollo "hot" lo calcola il sistema dalla rilevanza che il modello
--  assegna. Questo no: lo metti tu, a mano, dal pannello. Serve per i
--  pezzi che contano per una ragione che un punteggio non sa vedere —
--  un pezzo scritto apposta, un anniversario, una notizia che per questo
--  sito vale più di quanto valga per gli altri.
--
--  Per questo è una colonna sua e non un valore di rilevanza alto:
--  la rilevanza la riscrive il modello a ogni rielaborazione, questa
--  resta.
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se risponde #1060 la colonna c'era già, e va bene così.
-- =====================================================================

ALTER TABLE df_articles
  ADD COLUMN speciale TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'etichetta "special", messa a mano dal pannello'
      AFTER in_apertura;

CREATE INDEX idx_art_speciale ON df_articles (speciale, stato);
