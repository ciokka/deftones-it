-- =====================================================================
--  Correzioni dopo le prime tre bozze — 28/08/2026
-- =====================================================================

-- 1 ── colonna per l'editore vero -------------------------------------
-- Finora accreditavamo "Google News", che è il postino, non la fonte.
ALTER TABLE df_raw_items
  ADD COLUMN editore VARCHAR(120) NULL COMMENT 'la testata vera: NME, The Scotsman...'
  AFTER autore;


-- 2 ── ricava l'editore dagli item già raccolti ------------------------
-- Dal dominio dell'URL canonico, dove non è news.google.com.
UPDATE df_raw_items
SET editore = SUBSTRING_INDEX(
      SUBSTRING_INDEX(SUBSTRING_INDEX(url_canonico, '://', -1), '/', 1),
      'www.', -1)
WHERE editore IS NULL
  AND url_canonico NOT LIKE '%news.google.com%';


-- 3 ── le tre bozze di prova vanno rifatte ----------------------------
-- Avevano la fonte sbagliata. Rimettiamo i loro item in coda e
-- cancelliamo le bozze: al prossimo enrich vengono riscritte bene.
UPDATE df_raw_items SET stato = 'nuovo' WHERE stato = 'elaborato';
DELETE FROM df_articles WHERE stato = 'draft';


-- ── verifica ─────────────────────────────────────────────────────────
SELECT COALESCE(editore, '(nessuno)') AS testata, COUNT(*) AS quanti
FROM df_raw_items WHERE stato = 'nuovo'
GROUP BY testata ORDER BY quanti DESC;
