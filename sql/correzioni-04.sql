-- =====================================================================
--  Correzioni del 28/08 — fonti linkabili
--
--  Verificato che i link di Google News NON sono risolvibili: rispondono
--  200 con una pagina JavaScript, e il payload CBMi... è protobuf senza
--  URL in chiaro. Quindi cambiamo strategia:
--    · Google News resta per SCOPRIRE le notizie
--    · le testate dirette servono per LINKARLE
--    · il nome dell'editore viene dal tag <source> del feed, affidabile
-- =====================================================================

-- 1 ── cinque testate italiane con feed verificato --------------------
-- Danno URL veri e coprono i Deftones: è da qui che verranno i link.
INSERT INTO df_sources (nome, url_feed, tipo, lingua, peso, filtra_keyword, attivo) VALUES
('Rockol',              'https://www.rockol.it/rss/news',       'rss', 'it', 80, 1, 1),
('Rolling Stone Italia', 'https://www.rollingstone.it/feed/',   'rss', 'it', 80, 1, 1),
('Rumore',              'https://rumoremag.com/feed/',          'rss', 'it', 75, 1, 1),
('ImpattoSonoro',       'https://www.impattosonoro.it/feed/',   'rss', 'it', 70, 1, 1),
('Spettakolo',          'https://www.spettakolo.it/feed/',      'rss', 'it', 60, 1, 1);


-- 2 ── ributta in raccolta gli item Google senza editore --------------
-- Sono stati raccolti prima che il parser leggesse il tag <source>, e
-- quindi non hanno la testata. Cancellandoli, il prossimo ingest li
-- riprende dallo stesso feed (che conserva settimane di arretrato) e
-- questa volta con il nome dell'editore.
DELETE FROM df_raw_items
WHERE editore IS NULL
  AND url_canonico LIKE '%news.google.com%'
  AND stato IN ('nuovo','elaborato');


-- 3 ── via le bozze con la fonte sbagliata ----------------------------
DELETE FROM df_articles WHERE stato = 'draft';
UPDATE df_raw_items SET stato = 'nuovo' WHERE stato = 'elaborato';


-- ── verifica: dopo il prossimo ingest ────────────────────────────────
SELECT COALESCE(editore, '(nessuno)') AS testata, COUNT(*) AS quanti
FROM df_raw_items WHERE stato = 'nuovo'
GROUP BY testata ORDER BY quanti DESC;
