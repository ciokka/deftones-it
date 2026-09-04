-- =====================================================================
--  Il recupero dell'archivio 2021-2025
--
--  Gli indirizzi si scoprono dalla Wayback Machine e si datano
--  aprendo la pagina: molti risultano fuori dal periodo che ci
--  interessa — un articolo del 2010 può benissimo essere stato
--  archiviato nel 2023. Vanno segnati comunque, o alla prossima
--  passata li riapriremmo tutti da capo.
--
--  Perciò l'elenco degli stati accoglie "scartato_data", che vuol dire
--  "visto, datato, non è del periodo". Riusare "scartato_keyword"
--  avrebbe funzionato e avrebbe mentito: fra sei mesi, leggendo quel
--  campo, non si capirebbe più perché quell'articolo non è entrato.
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
-- =====================================================================

ALTER TABLE df_raw_items
  MODIFY COLUMN stato
    ENUM('nuovo','scartato_keyword','scartato_data','duplicato','elaborato','errore')
    NOT NULL DEFAULT 'nuovo';
