-- =====================================================================
--  Date sbagliate sugli articoli del recupero storico — 29/08/2026
--
--  enrich non impostava pubblicato_il alla creazione: restava vuota
--  fino alla pubblicazione, e in quel momento il pannello ci metteva
--  "adesso". Per il flusso quotidiano era quasi giusto — la notizia è
--  di oggi — ma per un anno di arretrato no: l'uscita di 'private
--  music' dell'agosto 2025 è finita datata oggi.
--
--  La data dell'articolo dev'essere quella del fatto che racconta.
--  Corretto nel codice; qui si sistemano le righe già scritte
--  prendendo la data dall'item di origine.
--
--  Non tocca l'archivio WordPress (categoria 'evergreen'): quelle date
--  vengono da wp_posts e sono giuste.
-- =====================================================================


-- 1 ── GUARDA PRIMA: quante e di quanto sono sbagliate ───────────────
SELECT a.stato,
       COUNT(*) AS quanti,
       MIN(DATE(r.pubblicato_il)) AS fatto_piu_vecchio,
       MAX(DATE(r.pubblicato_il)) AS fatto_piu_recente
FROM df_articles a
JOIN df_raw_items r ON r.id = a.raw_item_id
WHERE a.categoria <> 'evergreen'
  AND r.pubblicato_il IS NOT NULL
  AND (a.pubblicato_il IS NULL
       OR ABS(TIMESTAMPDIFF(HOUR, a.pubblicato_il, a.creato_il)) < 2)
GROUP BY a.stato;


-- 2 ── e qualche esempio, per controllare a occhio ───────────────────
SELECT a.id, a.stato,
       DATE(a.pubblicato_il) AS data_ora_sbagliata,
       DATE(r.pubblicato_il) AS data_giusta,
       a.titolo_it
FROM df_articles a
JOIN df_raw_items r ON r.id = a.raw_item_id
WHERE a.categoria <> 'evergreen'
  AND r.pubblicato_il IS NOT NULL
  AND (a.pubblicato_il IS NULL
       OR ABS(TIMESTAMPDIFF(HOUR, a.pubblicato_il, a.creato_il)) < 2)
ORDER BY r.pubblicato_il
LIMIT 25;


-- 3 ── LA CORREZIONE ─────────────────────────────────────────────────
-- Agisce solo dove la data manca o coincide con il momento della
-- creazione, cioè dove è chiaramente quella sbagliata. Se avessi
-- corretto una data a mano, quella resta.
UPDATE df_articles a
JOIN df_raw_items r ON r.id = a.raw_item_id
SET a.pubblicato_il = r.pubblicato_il
WHERE a.categoria <> 'evergreen'
  AND r.pubblicato_il IS NOT NULL
  AND (a.pubblicato_il IS NULL
       OR ABS(TIMESTAMPDIFF(HOUR, a.pubblicato_il, a.creato_il)) < 2);


-- ── verifica: come si distribuiscono adesso ──────────────────────────
SELECT YEAR(pubblicato_il) AS anno, MONTH(pubblicato_il) AS mese,
       COUNT(*) AS articoli
FROM df_articles
WHERE categoria <> 'evergreen' AND pubblicato_il IS NOT NULL
GROUP BY anno, mese ORDER BY anno, mese;
