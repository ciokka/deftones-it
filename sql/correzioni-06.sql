-- =====================================================================
--  Pulizia dell'archivio già importato — 28/08/2026
--
--  Due cose: i marcatori bilingui di qTranslate rimasti nei titoli, e
--  le notizie brevissime del passato che oggi fanno solo rumore.
--
--  ESEGUI UNA QUERY ALLA VOLTA e leggi le verifiche: la sezione 2
--  cancella dalla vista centinaia di articoli, e conviene guardare la
--  distribuzione prima di scegliere la soglia.
-- =====================================================================


-- =====================================================================
--  1. MARCATORI BILINGUI
--     qTranslate salvava le due lingue nello stesso campo:
--       <!--:it-->Intervista a Chino<!--:--><!--:en-->Interview<!--:-->
--     Teniamo la parte italiana e buttiamo il resto.
-- =====================================================================

-- 1a ── prima guarda quanti sono ─────────────────────────────────────
SELECT
  SUM(titolo_it   LIKE '%<!--:%' OR titolo_it   LIKE '%[:%')  AS titoli,
  SUM(sommario_it LIKE '%<!--:%' OR sommario_it LIKE '%[:%')  AS sommari,
  SUM(corpo_it    LIKE '%<!--:%' OR corpo_it    LIKE '%[:%')  AS corpi
FROM df_articles;


-- 1b ── forma classica <!--:it-->…<!--:--> ───────────────────────────
UPDATE df_articles
SET titolo_it = TRIM(REGEXP_REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(titolo_it, '<!--:it-->', -1), '<!--:-->', 1),
      '<!--:[a-z]{0,2}-->', ''))
WHERE titolo_it LIKE '%<!--:%';

UPDATE df_articles
SET sommario_it = TRIM(REGEXP_REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(sommario_it, '<!--:it-->', -1), '<!--:-->', 1),
      '<!--:[a-z]{0,2}-->', ''))
WHERE sommario_it LIKE '%<!--:%';

UPDATE df_articles
SET corpo_it = TRIM(REGEXP_REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(corpo_it, '<!--:it-->', -1), '<!--:-->', 1),
      '<!--:[a-z]{0,2}-->', ''))
WHERE corpo_it LIKE '%<!--:%';


-- 1c ── forma nuova [:it]…[:en]…[:] ──────────────────────────────────
UPDATE df_articles
SET titolo_it = TRIM(REGEXP_REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(titolo_it, '[:it]', -1), '[:', 1),
      '\\[:[a-z]{0,2}\\]', ''))
WHERE titolo_it LIKE '%[:it]%';

UPDATE df_articles
SET corpo_it = TRIM(REGEXP_REPLACE(
      SUBSTRING_INDEX(SUBSTRING_INDEX(corpo_it, '[:it]', -1), '[:', 1),
      '\\[:[a-z]{0,2}\\]', ''))
WHERE corpo_it LIKE '%[:it]%';


-- 1d ── entità HTML rimaste come testo (dell&#39;evento) ─────────────
UPDATE df_articles
SET titolo_it   = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(titolo_it,
                    '&#39;', ''''), '&#8217;', '’'), '&quot;', '"'),
                    '&#8220;', '“'), '&#8221;', '”'), '&nbsp;', ' '),
    sommario_it = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(sommario_it,
                    '&#39;', ''''), '&#8217;', '’'), '&quot;', '"'),
                    '&#8220;', '“'), '&#8221;', '”'), '&nbsp;', ' ')
WHERE titolo_it LIKE '%&#%' OR titolo_it LIKE '%&quot;%' OR titolo_it LIKE '%&nbsp;%'
   OR sommario_it LIKE '%&#%' OR sommario_it LIKE '%&quot;%' OR sommario_it LIKE '%&nbsp;%';

-- la & va per ultima, altrimenti trasformerebbe &amp;#39; in &#39;
UPDATE df_articles
SET titolo_it = REPLACE(titolo_it, '&amp;', '&'),
    sommario_it = REPLACE(sommario_it, '&amp;', '&')
WHERE titolo_it LIKE '%&amp;%' OR sommario_it LIKE '%&amp;%';


-- 1e ── verifica: non deve restare niente ────────────────────────────
SELECT COUNT(*) AS titoli_ancora_sporchi
FROM df_articles
WHERE titolo_it LIKE '%<!--:%' OR titolo_it LIKE '%[:it]%' OR titolo_it LIKE '%&#%';


-- =====================================================================
--  2. ARTICOLI TROPPO CORTI
--     Erano avvisi utili nel loro momento — "stasera live su MTV" — e
--     oggi non dicono più niente. Restano in tabella: cambia solo lo
--     stato, quindi la decisione è reversibile.
-- =====================================================================

-- 2a ── QUESTA GUARDALA PRIMA: quanti ne toglie ogni soglia ──────────
-- CHAR_LENGTH conta l'HTML, non il testo: un articolo con molti tag
-- risulta più lungo di quanto legga. Per questo le soglie sono alte.
SELECT CASE
         WHEN CHAR_LENGTH(COALESCE(corpo_it,'')) <  300 THEN 'a. sotto 300'
         WHEN CHAR_LENGTH(COALESCE(corpo_it,'')) <  600 THEN 'b. 300-600'
         WHEN CHAR_LENGTH(COALESCE(corpo_it,'')) < 1000 THEN 'c. 600-1000'
         WHEN CHAR_LENGTH(COALESCE(corpo_it,'')) < 2000 THEN 'd. 1000-2000'
         ELSE                                                'e. oltre 2000'
       END AS lunghezza,
       COUNT(*) AS quanti
FROM df_articles
WHERE categoria = 'evergreen' AND stato = 'draft'
GROUP BY lunghezza ORDER BY lunghezza;

-- 2b ── e guarda che aspetto hanno, prima di decidere ────────────────
SELECT id, DATE(pubblicato_il) AS data,
       CHAR_LENGTH(COALESCE(corpo_it,'')) AS caratteri, titolo_it
FROM df_articles
WHERE categoria = 'evergreen' AND stato = 'draft'
  AND CHAR_LENGTH(COALESCE(corpo_it,'')) < 600
ORDER BY caratteri
LIMIT 40;


-- 2c ── SOLO QUANDO SEI CONVINTO: togli dalla vista ──────────────────
-- Cambia 600 con la soglia che hai scelto guardando 2a e 2b.
-- Nessun articolo viene cancellato: cambia lo stato, e si torna
-- indietro con la query 2d.
UPDATE df_articles
SET stato = 'scartato'
WHERE categoria = 'evergreen'
  AND stato = 'draft'
  AND CHAR_LENGTH(COALESCE(corpo_it,'')) < 600;


-- 2d ── ripensamento: rimette in bozza quelli scartati per lunghezza ─
-- UPDATE df_articles
-- SET stato = 'draft'
-- WHERE categoria = 'evergreen' AND stato = 'scartato'
--   AND CHAR_LENGTH(COALESCE(corpo_it,'')) < 600;


-- ── com'è messo l'archivio adesso ────────────────────────────────────
SELECT stato, COUNT(*) AS quanti
FROM df_articles WHERE categoria = 'evergreen'
GROUP BY stato ORDER BY quanti DESC;
