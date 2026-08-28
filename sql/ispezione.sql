-- =====================================================================
--  Query di ispezione — da eseguire in phpMyAdmin, una alla volta
--  (seleziona la query e premi "Esegui selezione")
-- =====================================================================


-- 1 ── RESA PER FONTE ─────────────────────────────────────────────────
-- Quanto produce ogni feed e quanto di quello è davvero utile.
-- Una fonte con tanti "totale" e pochi "utili" costa tempo e non rende.
SELECT s.nome,
       COUNT(*)                          AS totale,
       SUM(r.stato = 'nuovo')            AS utili,
       SUM(r.stato = 'duplicato')        AS duplicati,
       SUM(r.stato = 'scartato_keyword') AS fuori_tema
FROM df_raw_items r
JOIN df_sources s ON s.id = r.source_id
GROUP BY s.nome
ORDER BY utili DESC;


-- 2 ── COSA ANDREBBE AL MODELLO ADESSO ───────────────────────────────
-- È la query che conta davvero: questi sono i titoli per cui pagheresti.
-- Leggila tutta. Se è piena di roba pertinente si procede, se è piena
-- di spazzatura si stringe il filtro prima di spendere un centesimo.
SELECT r.id, s.nome AS fonte,
       DATE(r.pubblicato_il) AS data_articolo,
       r.titolo
FROM df_raw_items r
JOIN df_sources s ON s.id = r.source_id
WHERE r.stato = 'nuovo'
ORDER BY r.pubblicato_il DESC
LIMIT 60;


-- 3 ── QUANTO È VECCHIO L'ARRETRATO ──────────────────────────────────
-- Serve a scegliere la finestra temporale del passo 2: non ha senso
-- tradurre una notizia di otto mesi fa per pubblicarla come novità.
SELECT CASE
         WHEN r.pubblicato_il IS NULL                       THEN 'senza data'
         WHEN r.pubblicato_il >= NOW() - INTERVAL 3 DAY     THEN 'ultimi 3 giorni'
         WHEN r.pubblicato_il >= NOW() - INTERVAL 7 DAY     THEN 'ultima settimana'
         WHEN r.pubblicato_il >= NOW() - INTERVAL 30 DAY    THEN 'ultimo mese'
         WHEN r.pubblicato_il >= NOW() - INTERVAL 365 DAY   THEN 'ultimo anno'
         ELSE 'più vecchio'
       END AS eta,
       COUNT(*) AS quanti
FROM df_raw_items r
WHERE r.stato = 'nuovo'
GROUP BY eta
ORDER BY quanti DESC;


-- 4 ── LE PAROLE PIÙ RICORRENTI NEI TITOLI SCARTATI ──────────────────
-- Controprova: se qui compaiono termini pertinenti, il filtro è troppo
-- stretto e sta buttando via notizie buone.
SELECT r.titolo
FROM df_raw_items r
WHERE r.stato = 'scartato_keyword'
ORDER BY RAND()
LIMIT 30;


-- =====================================================================
--  CORREZIONE da applicare (vedi conversazione)
-- =====================================================================

-- Attiva il filtro keyword anche sui feed Google News
UPDATE df_sources SET filtra_keyword = 1 WHERE nome LIKE 'Google News%';

-- Ricontrolla con questa regola i "nuovi" già raccolti: quelli che non
-- contengono nessuna keyword nel titolo diventano scartati. Non cancella
-- nulla, cambia solo lo stato, ed è reversibile.
UPDATE df_raw_items
SET stato = 'scartato_keyword'
WHERE stato = 'nuovo'
  AND LOWER(CONCAT(titolo, ' ', COALESCE(estratto, ''))) NOT REGEXP
      'deftones|chino moreno|stephen carpenter|abe cunningham|frank delgado|sergio vega|chi cheng|crosses';
