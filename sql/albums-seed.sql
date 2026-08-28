-- =====================================================================
--  Discografia — dati di base
--
--  Fonte: MusicBrainz (release-group), interrogato il 28/08/2026.
--  L'mbid permette di ritrovare la pubblicazione in futuro e di
--  arricchirla senza doverla riconoscere per titolo.
--
--  descrizione_it e tracklist restano NULL: le riempie il passo
--  successivo, che deve lavorare su fonti verificabili e non sulla
--  memoria del modello. Un anno di uscita sbagliato in una scheda
--  evergreen resta lì per anni.
-- =====================================================================

INSERT INTO df_albums (slug, titolo, tipo, anno, data_uscita, etichetta, mbid, ordine) VALUES
('adrenaline', 'Adrenaline', 'album', 1995, '1995-10-02', NULL, '0b4ea447-444c-34ba-ba87-96496f2e15b8', 10),
('around-the-fur', 'Around the Fur', 'album', 1997, '1997-07-31', NULL, '6f935b80-160f-3787-97d6-a4ce342cf8c5', 20),
('white-pony', 'White Pony', 'album', 2000, '2000-04-27', NULL, 'a7d33e96-8e09-3aff-8629-44812a1b8489', 30),
('deftones-2003', 'Deftones', 'album', 2003, '2003-05-19', NULL, 'f423c6e6-410c-3b43-94ec-8f7a2c02f052', 40),
('saturday-night-wrist', 'Saturday Night Wrist', 'album', 2006, '2006-10-25', 'Maverick', '82be2ea8-9c50-3024-a1be-cf35c2e5fd69', 50),
('diamond-eyes', 'Diamond Eyes', 'album', 2010, '2010-04-23', NULL, 'f21cec66-4e3a-42fc-b7e4-59b17bed0cca', 60),
('koi-no-yokan', 'Koi no Yokan', 'album', 2012, '2012-11-12', NULL, '87338b87-34e2-4f11-8e2e-600636b6dbcb', 70),
('gore', 'Gore', 'album', 2016, '2016-04-08', NULL, '4c0a6c9b-090b-4159-a359-a328d94c50d3', 80),
('ohms', 'Ohms', 'album', 2020, '2020-09-25', NULL, 'cc2075d9-3f46-4d74-82a9-31492bf2a7e4', 90),
('private-music', 'private music', 'album', 2025, '2025-08-22', NULL, '5e9a3ca5-82ce-47da-9409-83f6f953b4a2', 100),
('eros', 'Eros', 'album', 2026, '2026-06-22', NULL, 'cd1e2fda-d597-42e8-83fc-220358a98e7b', 110),
('b-sides-rarities', 'B‐Sides & Rarities', 'raccolta', 2005, '2005-10-04', NULL, '78b906d6-0cb5-3570-bce6-30e28771ed86', 120),
('covers', 'Covers', 'raccolta', NULL, NULL, NULL, '82be9e45-65af-3d06-b673-877c0eafe8a6', 130)
ON DUPLICATE KEY UPDATE
  titolo = VALUES(titolo), anno = VALUES(anno),
  data_uscita = VALUES(data_uscita), mbid = VALUES(mbid), ordine = VALUES(ordine);


-- ── verifica ─────────────────────────────────────────────────────────
SELECT ordine, tipo, anno, titolo, etichetta,
       CASE WHEN descrizione_it IS NULL THEN 'da scrivere' ELSE 'fatta' END AS scheda
FROM df_albums ORDER BY ordine;
