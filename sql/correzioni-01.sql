-- =====================================================================
--  Correzioni dopo il primo giro di ingest — 27/08/2026
--  Eseguile in ordine. Nessuna cancella dati: cambiano solo stati e
--  flag, tutto reversibile.
-- =====================================================================

-- 1 ── nuovo stato per l'arretrato -----------------------------------
-- Serve a distinguere "non pertinente" da "pertinente ma vecchio".
ALTER TABLE df_raw_items
  MODIFY stato ENUM('nuovo','scartato_keyword','troppo_vecchio',
                    'duplicato','elaborato','errore')
  NOT NULL DEFAULT 'nuovo';


-- 2 ── filtro keyword anche sui feed Google News ----------------------
-- Incide poco (quasi tutti quei titoli contengono già "Deftones") ma
-- ferma i casi tipo "Nialler9 Dublin Gig Guide: Super Furry Animals...".
UPDATE df_sources SET filtra_keyword = 1 WHERE nome LIKE 'Google News%';


-- 3 ── Reddit spento ---------------------------------------------------
-- I "nuovi" che produceva erano "T Shirt", "Deftones Baby!!", "Duel 29".
-- Non sono notizie, e intanto il filtro scartava i post con contenuto
-- perché non nominano la band nel titolo. Sbagliato in entrambi i versi.
UPDATE df_sources SET attivo = 0 WHERE nome = 'Reddit r/Deftones';


-- 4 ── finestra temporale sull'arretrato ------------------------------
-- Toglie dalla coda tutto ciò che ha più di 14 giorni. Resta in tabella,
-- consultabile, semplicemente non verrà mai mandato al modello.
UPDATE df_raw_items
SET stato = 'troppo_vecchio'
WHERE stato = 'nuovo'
  AND pubblicato_il IS NOT NULL
  AND pubblicato_il < NOW() - INTERVAL 14 DAY;


-- 5 ── i post Reddit già raccolti escono dalla coda --------------------
UPDATE df_raw_items r
JOIN df_sources s ON s.id = r.source_id
SET r.stato = 'scartato_keyword'
WHERE s.nome = 'Reddit r/Deftones' AND r.stato = 'nuovo';


-- =====================================================================
--  VERIFICA — cosa resta davvero in coda
-- =====================================================================
SELECT stato, COUNT(*) AS quanti
FROM df_raw_items GROUP BY stato ORDER BY quanti DESC;

-- E la coda vera, quella che andrà al modello:
SELECT r.id, s.nome AS fonte, DATE(r.pubblicato_il) AS data_articolo, r.titolo
FROM df_raw_items r JOIN df_sources s ON s.id = r.source_id
WHERE r.stato = 'nuovo'
ORDER BY r.pubblicato_il DESC;
