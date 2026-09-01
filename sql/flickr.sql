-- =====================================================================
--  Il catalogo accoglie anche Flickr
--
--  Finora la colonna che identifica una fotografia si chiamava
--  "commons", perché era l'unico posto da cui venivano. Adesso non è
--  più vero, e una colonna che si chiama commons contenente un
--  identificativo di Flickr è il genere di cosa che fra sei mesi fa
--  perdere mezz'ora. Diventa "riferimento", con accanto la provenienza.
--
--  Gli identificativi non si scontrano: quelli di Flickr portano il
--  prefisso "flickr:", quelli di Commons sono nomi di file.
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se risponde che la colonna non esiste, era già stata rinominata.
-- =====================================================================

ALTER TABLE df_immagini
  CHANGE COLUMN commons riferimento VARCHAR(300) NOT NULL
    COMMENT 'il nome del file su Commons, o flickr:<id>',
  ADD COLUMN provenienza VARCHAR(20) NOT NULL DEFAULT 'commons'
    COMMENT 'commons o flickr' AFTER riferimento;

-- L'indice unico continua a chiamarsi uq_img_commons: rinominarlo
-- vorrebbe dire cancellarlo e rifarlo, e per un attimo il catalogo
-- resterebbe senza la garanzia che impedisce i doppioni.
