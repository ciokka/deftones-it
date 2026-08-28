-- =====================================================================
--  Uniforma il nome delle testate — 28/08/2026
--
--  Finora per i feed diretti ricavavo la testata dal dominio, quindi la
--  stessa fonte compariva in due forme: "loudwire.com" e "Loudwire".
--  Il nome giusto ce l'abbiamo già in df_sources.
-- =====================================================================

UPDATE df_raw_items r
JOIN df_sources s ON s.id = r.source_id
SET r.editore = s.nome
WHERE s.url_feed NOT LIKE '%news.google.com%'
  AND (r.editore IS NULL OR r.editore LIKE '%.%');

-- Le due bozze già scritte: allineiamo anche quelle
UPDATE df_articles SET fonte_nome = 'Loudwire' WHERE fonte_nome = 'loudwire.com';
UPDATE df_articles SET fonte_nome = 'NME'      WHERE fonte_nome = 'nme.com';


-- ── verifica: ogni testata una volta sola ────────────────────────────
SELECT COALESCE(editore, '(nessuno)') AS testata, COUNT(*) AS quanti
FROM df_raw_items
GROUP BY testata ORDER BY quanti DESC;
