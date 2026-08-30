-- =====================================================================
--  Quali articoli vanno in apertura
--
--  Il carosello della home mostra tre notizie. Di suo prende le tre più
--  recenti; questa colonna permette di fissarne una che deve restare lì
--  anche quando ne escono di nuove — un pezzo scritto apposta, o una
--  notizia che conta più della cronaca del giorno.
--
--  Le fissate vengono prima, poi si completa con le più recenti: se non
--  ne fissi nessuna il comportamento resta quello di adesso.
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se risponde #1060 la colonna c'era già, e va bene così.
-- =====================================================================

ALTER TABLE df_articles
  ADD COLUMN in_apertura TINYINT(1) NOT NULL DEFAULT 0
      COMMENT 'fissato nel carosello della home'
      AFTER stato;
