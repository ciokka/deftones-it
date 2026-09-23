-- =====================================================================
--  deftones.it — aggiunta della tabella delle visite.
--
--  Il database esiste già, e qui si aggiunge una tabella e nient'altro.
--  Rieseguirlo non fa danni.
--
--    NAS         ssh nas '/volume1/web/deftones/.locale/db.sh' < sql/visite.sql
--    produzione  cPanel -> phpMyAdmin -> database -> tab SQL -> incolla
--
--  Se il prefisso delle tabelle non è df_, cambialo qui prima di
--  eseguire: il codice lo legge da config.php, questo file no.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- VISITE — quante volte si legge ogni pagina, e niente di più.
--
--     Una riga per giorno, pagina e provenienza, con un numero che
--     cresce. Non c'è una riga per visitatore, e quindi non c'è niente
--     che possa diventarlo: né IP, né browser, né ora esatta. Il
--     prezzo è che si contano le letture e non le persone — chi torna
--     tre volte conta tre. È lo stesso patto del cuore sotto gli
--     articoli: un numero indicativo, in cambio di non schedare
--     nessuno.
--
--     origine è il solo dominio da cui si arriva ("google.com"), mai
--     l'indirizzo intero: quello può contenere una ricerca, o una
--     pagina privata di chi linka. 'interno' se si arriva da una
--     pagina del sito, vuoto se non si sa.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS df_visite (
  giorno    DATE         NOT NULL,
  percorso  VARCHAR(255) NOT NULL,
  origine   VARCHAR(100) NOT NULL DEFAULT '',
  visite    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (giorno, percorso, origine),
  KEY idx_visite_percorso (percorso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
