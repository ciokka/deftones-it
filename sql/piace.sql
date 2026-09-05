-- =====================================================================
--  Il contatore dei "mi piace"
--
--  Un numero sull'articolo e basta: niente tabella dei voti, niente
--  identificatori, niente indirizzi IP. Il sito non sa chi ha premuto
--  il cuore e non vuole saperlo — è la stessa scelta per cui non ha
--  cookie e non chiama server altrui.
--
--  A ricordare che tu l'hai già premuto ci pensa il tuo browser, nel
--  suo spazio locale, e solo dopo che l'hai premuto: prima del clic non
--  viene scritto niente da nessuna parte.
--
--  Il prezzo di questa scelta è che il numero è indicativo, non un
--  conteggio certificato. Su un sito di fan va benissimo così.
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se risponde #1060 la colonna c'era già.
-- =====================================================================

ALTER TABLE df_articles
  ADD COLUMN piaciuto INT UNSIGNED NOT NULL DEFAULT 0
      COMMENT 'quante volte è stato premuto il cuore';
