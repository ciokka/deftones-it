-- =====================================================================
--  Ricerca a testo pieno
--
--  Lo schema iniziale aveva già un indice su titolo e sommario. Per
--  l'archivio non basta: seicento articoli del vecchio sito hanno il
--  testo in corpo_it, e il sommario è solo un estratto delle prime
--  righe. Cercare "bataclan" o "terry date" non trovava niente se la
--  parola compariva a metà pezzo.
--
--  Un indice solo su tutt'e tre le colonne, invece di tre indici: MySQL
--  può usare un solo indice per ogni MATCH, e con tre indici separati
--  servirebbero tre MATCH in OR, con tre punteggi da mettere d'accordo.
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se risponde #1061 l'indice c'era già, e va bene così.
--
--  Ci mette qualche secondo: deve leggere tutti gli articoli.
-- =====================================================================

ALTER TABLE df_articles
  ADD FULLTEXT KEY ft_art_tutto (titolo_it, sommario_it, corpo_it);
