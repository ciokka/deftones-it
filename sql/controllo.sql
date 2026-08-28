-- =====================================================================
--  Query di controllo — dopo ogni giro di enrich
--  Eseguile una alla volta (seleziona la query e "Esegui selezione")
-- =====================================================================


-- 1 ── LE BOZZE, con la fonte accreditata ────────────────────────────
-- La colonna che ci interessa oggi è fonte_nome: deve contenere testate
-- vere (nme.com, thescotsman.com, Kerrang!), non "Google News".
SELECT a.rilevanza,
       a.categoria,
       a.attendibilita,
       a.fonte_nome,
       a.titolo_it,
       a.sommario_it,
       a.fonte_url,
       a.tag,
       a.creato_il
FROM df_articles a
WHERE a.stato = 'draft'
ORDER BY a.rilevanza DESC;


-- 2 ── L'EDITORE È STATO RICAVATO? ───────────────────────────────────
-- "(nessuno)" sono gli item con URL Google non ancora risolto: normale,
-- ma se sono la maggioranza il credito alle fonti resta debole.
SELECT COALESCE(editore, '(nessuno)') AS testata,
       COUNT(*) AS quanti
FROM df_raw_items
GROUP BY testata
ORDER BY quanti DESC;


-- 3 ── COSA È COSTATO ────────────────────────────────────────────────
-- Tariffe Claude Opus 5: 5 $ / milione in, 25 $ / milione out.
SELECT job,
       DATE(iniziato_il)                            AS giorno,
       COUNT(*)                                     AS giri,
       SUM(item_elaborati)                          AS articoli,
       SUM(token_in)                                AS token_in,
       SUM(token_out)                               AS token_out,
       ROUND(SUM(token_in)/1000000*5.00
           + SUM(token_out)/1000000*25.00, 4)       AS dollari,
       ROUND((SUM(token_in)/1000000*5.00
           + SUM(token_out)/1000000*25.00)*0.92, 4) AS euro
FROM df_run_log
WHERE job = 'enrich'
GROUP BY job, DATE(iniziato_il)
ORDER BY giorno DESC;


-- 4 ── QUANTE FONTI SONO STATE FUSE IN OGNI ARTICOLO ─────────────────
-- Il controllo sul raggruppamento: se il concerto di Outbreak compare
-- con una sola fonte, la fusione non ha funzionato.
SELECT a.titolo_it,
       a.fonte_nome AS fonte_principale,
       (SELECT COUNT(*) FROM df_raw_items r
         WHERE r.stato = 'elaborato'
           AND r.visto_il >= a.creato_il - INTERVAL 1 DAY) AS item_elaborati_totali
FROM df_articles a
WHERE a.stato = 'draft'
ORDER BY a.rilevanza DESC;


-- 5 ── COSA RESTA IN CODA ────────────────────────────────────────────
SELECT stato, COUNT(*) AS quanti
FROM df_raw_items
GROUP BY stato
ORDER BY quanti DESC;
