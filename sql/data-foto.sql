-- =====================================================================
--  La data di scatto delle fotografie
--
--  Serve a scegliere: una foto del 2009 su una notizia di oggi stona,
--  e senza la data non c'è modo di accorgersene guardando la miniatura.
--
--  VARCHAR e non DATE perché Commons non sempre sa il giorno: di certe
--  foto conosce solo l'anno, di altre l'anno e il mese. Un DATE
--  costringerebbe a inventare un primo gennaio, e una data inventata è
--  peggio di una data parziale.
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se risponde #1060 la colonna c'era già.
--
--  Dopo, va rifatta la raccolta per riempirla:
--    dischi/copertine.php --raccogli
--  Le righe che ci sono già vengono aggiornate, non duplicate.
-- =====================================================================

ALTER TABLE df_immagini
  ADD COLUMN data_foto VARCHAR(10) NULL
      COMMENT 'quando è stata scattata: 2016-10-28, oppure 2016-10, oppure 2016'
      AFTER altezza;
