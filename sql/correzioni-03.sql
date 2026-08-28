-- =====================================================================
--  Correzioni dopo le bozze del 28/08 ore 12:09
--
--  Cosa non andava:
--   · fonte_nome diceva "Google News", che è il postino, non la testata
--   · fonte_url puntava al redirect di Google, non all'articolo vero
--   · nel testo restavano sequenze à al posto delle accentate
--
--  Le prime due si risolvono nel codice (enrich.php risolve un redirect
--  per articolo, al momento di salvare). Qui rimettiamo solo le bozze
--  in coda perché vengano riscritte con i dati giusti.
-- =====================================================================

-- Rimetti in lavorazione gli item già elaborati e cancella le bozze
UPDATE df_raw_items SET stato = 'nuovo' WHERE stato = 'elaborato';
DELETE FROM df_articles WHERE stato = 'draft';


-- ── verifica: dopo il prossimo enrich, fonte_nome e fonte_url ────────
SELECT rilevanza, fonte_nome, fonte_url, titolo_it
FROM df_articles WHERE stato = 'draft'
ORDER BY rilevanza DESC;
