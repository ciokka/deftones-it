-- =====================================================================
--  Le ricerche delle fotografie, dal pannello
--
--  Le parole con cui si cercano le foto stavano dentro copertine.php:
--  un elenco in PHP, che per aggiungerci "deftones 2027" richiedeva di
--  aprire l'editor, ricordarsi la sintassi di un array e ricaricare il
--  file sul server. Cioè: non si aggiungevano mai.
--
--  Qui dentro ci stanno tutte e due le liste, distinte dalla colonna
--  fonte:
--
--    openverse   una domanda a testo libero — "deftones live" — che
--                Openverse cerca nel titolo, nella descrizione e nei tag
--    commons     il nome di una categoria di Wikimedia Commons, senza
--                il prefisso "Category:". Non è una ricerca a testo
--                libero: o la categoria esiste con quel nome esatto, o
--                non torna niente
--
--  Le tre colonne dei conti — ultimo_giro, viste, nuove — le riempie la
--  raccolta. Servono a rispondere alla domanda che ci si fa appena si
--  possono aggiungere parole a volontà: questa qui sta portando foto, o
--  sta solo consumando quota?
--
--  --- Da eseguire una volta sola, in phpMyAdmin. ---
--  Se la tabella c'è già, IF NOT EXISTS non fa niente e non interrompe
--  niente. Le INSERT sono IGNORE per lo stesso motivo.
--
--  Finché non la si esegue non si rompe niente: senza questa tabella il
--  programma ripiega sugli elenchi scritti in PHP, che sono gli stessi
--  che le INSERT qui sotto ci mettono dentro.
-- =====================================================================

CREATE TABLE IF NOT EXISTS df_ricerche (
  id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fonte       ENUM('openverse','commons') NOT NULL DEFAULT 'openverse',
  domanda     VARCHAR(120) NOT NULL
              COMMENT 'le parole da cercare, o il nome della categoria',
  soggetto    VARCHAR(40)  NOT NULL DEFAULT 'band'
              COMMENT 'a chi attribuire le foto che tornano: band, chino, stephen…',
  pagine      TINYINT UNSIGNED NOT NULL DEFAULT 4
              COMMENT 'quante pagine di risultati chiedere — solo Openverse',
  attiva      TINYINT(1)   NOT NULL DEFAULT 1
              COMMENT 'sospesa resta scritta ma non viene interrogata',
  ultimo_giro DATETIME     NULL,
  viste       SMALLINT UNSIGNED NOT NULL DEFAULT 0
              COMMENT 'quante foto ha riportato l''ultimo giro',
  nuove       SMALLINT UNSIGNED NOT NULL DEFAULT 0
              COMMENT 'quante di quelle non erano già in catalogo',
  aggiunta_il DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  -- La stessa parola su due archivi diversi è lecita: "Deftones" è una
  -- categoria di Commons e anche una domanda sensata per Openverse.
  UNIQUE KEY uq_ric_domanda (fonte, domanda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --- Le ricerche che c'erano, trasferite -----------------------------
--
-- Openverse cerca anche nei tag, e per le persone ci vuole comunque
-- "deftones": "chino" da solo riporta indietro mezzo mondo.
--
-- Le ultime quattro non cercano una persona ma una situazione. Per un
-- pezzo scritto a mano serve una fotografia che c'entri con quello che
-- racconta, e "deftones" e basta riporta sempre gli stessi ritratti di
-- scena: chi cerca il palco, la folla o il festival deve chiederlo.
-- Restano soggetto 'band' perché valgono per qualunque articolo.
--
-- Gli anni servono a farsi dare il recente: le altre domande pescano dal
-- mucchio, e nel mucchio il 2011 pesa vent'anni più del 2025.

INSERT IGNORE INTO df_ricerche (fonte, domanda, soggetto, pagine) VALUES
  ('openverse', 'deftones',                     'band',    8),
  ('openverse', 'deftones chino moreno',        'chino',   4),
  ('openverse', 'deftones stephen carpenter',   'stephen', 4),
  ('openverse', 'deftones sergio vega',         'sergio',  4),
  ('openverse', 'deftones abe cunningham',      'abe',     4),
  ('openverse', 'deftones frank delgado',       'frank',   4),
  ('openverse', 'deftones chi cheng',           'chi',     4),
  ('openverse', 'deftones live',                'band',    8),
  ('openverse', 'deftones concert',             'band',    8),
  ('openverse', 'deftones festival',            'band',    8),
  ('openverse', 'deftones tour',                'band',    8),
  ('openverse', 'deftones 2025',                'band',    8),
  ('openverse', 'deftones 2026',                'band',    8),
  ('openverse', 'deftones private music',       'band',    8);

-- Su Commons sono categorie e non ricerche a testo libero per un motivo
-- pratico: cercare "Chino Moreno" restituisce diciannovemila file, quasi
-- tutti di altre persone che si chiamano così. La categoria contiene
-- solo lui.
--
-- Le categorie con soggetto 'band' vengono esplorate anche nelle
-- sottocategorie — i singoli concerti: Hellfest 2010, Rock im Park
-- 2022 — ed è lì che stanno le foto buone.
--
-- Abe Cunningham, Frank Delgado e Chi Cheng non hanno una categoria
-- propria: gli articoli che li riguardano ripiegano sulle foto di
-- gruppo, che è meglio di una foto sbagliata.

INSERT IGNORE INTO df_ricerche (fonte, domanda, soggetto) VALUES
  ('commons', 'Deftones',          'band'),
  ('commons', 'Chino Moreno',      'chino'),
  ('commons', 'Stephen Carpenter', 'stephen'),
  ('commons', 'Sergio Vega',       'sergio');
