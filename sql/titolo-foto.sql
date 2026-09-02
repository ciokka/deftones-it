-- =====================================================================
--  Il titolo della fotografia
--
--  Serve a scegliere. Le foto di Flickr non hanno la data di scatto:
--  Openverse non la restituisce, e andarsela a prendere dalle pagine di
--  Flickr vorrebbe dire ignorare il loro robots.txt, che vieta la
--  raccolta automatica a chiunque non sia in una lista di motori di
--  ricerca. Quindi la data, per quelle, non l'avremo.
--
--  Ma il titolo sì, e spesso dice di più: "Deftones @ Hellfest 2010"
--  racconta l'occasione oltre all'anno, che è esattamente quello che
--  serve a chi sta scrivendo un pezzo e cerca una fotografia che
--  c'entri.
--
--  Per Commons ci finisce il nome del file senza estensione, che è già
--  una didascalia: "Rock in Pott 2013 - Deftones 22".
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se risponde #1060 la colonna c'era già.
--
--  Dopo, va rifatta la raccolta per riempirla:
--    copertine.php --raccogli          (Commons, subito)
--    copertine.php --raccogli-altre    (Openverse, quando si sblocca)
--  Le righe che ci sono già vengono aggiornate, non duplicate.
-- =====================================================================

ALTER TABLE df_immagini
  ADD COLUMN titolo VARCHAR(200) NULL
      COMMENT 'come l''ha intitolata chi l''ha scattata'
      AFTER riferimento;
