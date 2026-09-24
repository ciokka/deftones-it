-- =====================================================================
--  deftones.it — aggiunta della tabella delle impostazioni.
--
--  Come visite.sql: il database esiste già, e qui si aggiunge una
--  tabella e nient'altro. Rieseguirlo non fa danni. Non è nemmeno
--  indispensabile: il sito la crea da sé la prima volta che si salva
--  una scelta dal pannello (impostaValore() in lib/modello.php).
--
--    NAS         .locale/db.sh < sql/impostazioni.sql
--    produzione  cPanel -> phpMyAdmin -> database -> tab SQL -> incolla
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- IMPOSTAZIONI — le scelte fatte dal pannello invece che da
--     config.php. Per ora il modello: 'modello' è 'auto' o un id,
--     'modello_auto' è l'Opus che l'automatico ha scelto, e l'elenco
--     dei modelli dell'API ci resta in copia per un giorno.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS df_impostazioni (
  chiave         VARCHAR(60) NOT NULL,
  valore         TEXT        NOT NULL,
  aggiornato_il  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (chiave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
