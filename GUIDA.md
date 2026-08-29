# deftones.it — guida al sistema

Questo documento è la memoria del progetto. La conversazione in cui è
stato costruito non ci sarà più; questo file sì.

Contiene cosa fa ogni pezzo, dove sta, come si aggiorna, cosa guardare
quando qualcosa non va — e **perché** alcune scelte sono state fatte
così: sono quelle che fra sei mesi sembreranno arbitrarie e che invece
hanno un motivo.

---

## 1. Cos'è

Un sito di notizie sui Deftones in italiano che si aggiorna da solo, con
sopra vent'anni di archivio recuperato dal vecchio WordPress.

Il flusso quotidiano:

```
  17 feed RSS
      ↓  ingest.php · ogni 4 ore, gratis
  df_raw_items          scarta duplicati, fuori tema, troppo vecchi
      ↓  enrich.php · ogni 4 ore, ~6 centesimi
  df_articles (draft)   raggruppa per evento, scrive in italiano
      ↓  TU, dal pannello
  online
```

Niente va online da solo. Ogni cosa nasce in bozza e aspetta la tua
approvazione — è la scelta che tiene il sito tuo invece che di un
programma.

---

## 2. Dove sta cosa

### Sul server

```
/home/bpdefton/
├── deftones/app/              il codice — FUORI da public_html
│   ├── config.php             credenziali. Non è in git, non lo tocca
│   │                          nessun deploy. Se lo perdi, lo rifai da
│   │                          config.example.php
│   ├── admin.php              il pannello
│   ├── cron/                  gli otto script
│   ├── lib/                   funzioni condivise
│   ├── views/                 i template
│   ├── cache/                 pagine generate — si svuota da sé
│   └── logs/                  un file per script, si ruota a 2 MB
│
├── public_html/               ciò che il web può vedere
│   ├── index.php              unico punto d'ingresso
│   ├── .htaccess              manda tutto a index.php
│   ├── assets/                CSS, font, pattern, immagini
│   └── media/                 le immagini del vecchio WordPress
│
├── repositories/deftones-it/  il clone git che cPanel aggiorna
└── archivio-wp/               il vecchio WordPress, spento e fuori dal web
```

`config.php` sta fuori da `public_html` di proposito: contiene la
password del database e la chiave API, e da lì non è raggiungibile dal
web nemmeno se un giorno PHP smettesse di funzionare.

### Nel repository

```
app/          → /home/bpdefton/deftones/app/
web/          → /home/bpdefton/public_html/
sql/          da eseguire a mano in phpMyAdmin, mai automatico
strumenti/    script che girano sul Mac, non sul server
materiali/    logo, pattern, immagine di anteprima
.cpanel.yml   dice al deploy dove va ogni cosa
```

---

## 3. Come si aggiorna

Il Mac governa, il server segue.

1. Le modifiche si committano e si spingono su `github.com/ciokka/deftones-it`
2. cPanel → **Git™ Version Control** → *Pull or Deploy*
3. **Update from Remote** — scarica i commit
4. **Deploy HEAD Commit** — copia i file al loro posto

**Sono due bottoni distinti e servono entrambi.** Il primo aggiorna il
repository sul server, il secondo aggiorna ciò che gira. Se il *Last
Deployed SHA* non coincide con *HEAD Commit*, il deploy non è stato
fatto.

Il deploy svuota anche la cache delle pagine.

### Annullare

cPanel non sa tornare indietro. Dal Mac:

```
git revert <sha>
git push
```

poi *Update from Remote* e *Deploy*. Si usa `revert` e non `reset`: crea
un commit nuovo che annulla il precedente invece di riscrivere il
passato, e si può annullare a sua volta.

---

## 4. I cron

| Quando | Cosa | Comando |
|---|---|---|
| `5 */4` | raccolta | `ingest.php >/dev/null` |
| `35 */4` | scrittura | `enrich.php >/dev/null` |
| `15 9` | riepilogo | `riepilogo.php >/dev/null` |
| `*/30` | richieste | `scrivi-richieste.php --una >/dev/null` |

Percorso completo del PHP: `/opt/cpanel/ea-php83/root/usr/bin/php -q`
seguito da `/home/bpdefton/deftones/app/cron/<script>.php`.

**`>/dev/null` senza `2>&1`**, e non è un dettaglio: l'output normale
sparisce, ma gli errori restano su stderr e cPanel te li manda per
email. Aggiungendo `2>&1` spariscono anche quelli, e un guasto diventa
silenzioso. Perché la mail parta, il campo email in cima alla pagina
Processi Cron dev'essere compilato.

Le richieste girano ogni mezz'ora perché a vuoto non costano niente: lo
script prende un lock, non trova nulla in attesa ed esce. È la differenza
fra ordinare un articolo e averlo entro mezz'ora, o il giorno dopo.

Gli altri script sono **strumenti una tantum**: si lanciano a mano
creando un cron temporaneo, non si programmano. Un `crea-temi.php`
lasciato giornaliero rifarebbe le raccolte ogni notte, con nomi diversi
ogni volta; un `enrich.php --tutto` quotidiano è una spesa senza tetto,
perché `--tutto` ripete il ciclo finché la coda non è vuota.

---

## 5. Gli script

### ingest.php — raccoglie
Interroga 17 feed. Per ogni articolo: già visto? contiene una parola
chiave? è un doppione di un titolo già arrivato? è più vecchio di 14
giorni? Solo ciò che supera tutto entra in coda.

Non chiama l'API, quindi **non costa nulla**. Usa il conditional GET: un
feed immutato risponde 304 e non viene scaricato. Un feed che sbaglia
dieci volte di fila si spegne da solo.

### enrich.php — scrive
Due tipi di chiamata. Una raggruppa gli item per **evento reale** —
quattordici recensioni dello stesso concerto sono una notizia sola — e
assegna una rilevanza. Poi una chiamata per ogni evento sopra soglia,
che scrive l'articolo in italiano.

È il cuore del risparmio: gli eventi sotto soglia vengono archiviati
**senza spendere una chiamata**.

- `--prova` solo il raggruppamento, non scrive nulla (~6 centesimi)
- `--tutto` ripete il ciclo finché la coda è vuota — per le code grosse

### riepilogo.php — avvisa
Manda una mail con le bozze nuove, i guasti, quanto resta da rivedere e
quanto hai speso. **Parte solo se c'è qualcosa da dire.**

Ha un guardiano: se l'ultima raccolta riuscita ha più di 12 ore, quello
è di per sé un guasto e la mail parte comunque. Senza, il silenzio di un
sistema fermo sarebbe identico al silenzio di un sistema sereno.

- `--prova` stampa e non invia
- `--forza` invia comunque, per verificare la consegna

### importa-wp.php — l'archivio
Legge `wp_posts` (stesso database), ripulisce l'HTML del 2005, recupera
i video Flash convertendoli in iframe, toglie le immagini morte,
scollega i rimandi a sezioni sparite, tiene solo l'italiano dai campi
bilingui di qTranslate. Ripetibile: aggiorna invece di duplicare.

- `--analisi` riporta cosa contiene l'archivio senza scrivere

### valuta-archivio.php — sceglie
Fa leggere l'archivio al modello a gruppi di 25 e assegna verdetto,
rilevanza e motivo. Sui 575 di partenza: 265 tenuti, 310 scartati, 1,60 €.

Serviva perché contare i caratteri non funziona — un articolo di 400
può essere denso, uno di 1500 pieno di tag può non dire niente — e
perché tutte le bozze importate avevano rilevanza 50, quindi ordinarle
per rilevanza non ordinava nulla.

### crea-temi.php — raggruppa
Trova le **raccolte** dentro l'archivio: storie raccontate a puntate che
sparse si perdono. Due fasi — prima propone i temi guardando tutto, poi
assegna gli articoli con quell'elenco fisso davanti.

### recupera-storico.php — l'arretrato
Google News accetta gli intervalli di date nella query, quindi si può
chiedere un mese alla volta. Ignora la finestra dei 14 giorni, che qui
andrebbe contro lo scopo. Gratis.

- `--mesi=6` per un periodo più corto

### scrivi-richieste.php — su commissione
Prende le richieste dal pannello e le scrive. **Due chiamate**: nella
prima il modello cerca sul web e riferisce cosa ha trovato, nella
seconda scrive avendo davanti solo ciò che ha letto.

Costa **fra 0,50 e 1,80 €** ad articolo — dieci volte una notizia
dell'aggregatore. È il prezzo del far cercare invece che ricordare, e
si giustifica solo sui contenuti che resteranno anni.

- `--una` ne lavora una sola

---

## 6. Le tabelle

| Tabella | Cosa contiene |
|---|---|
| `df_sources` | i feed, con peso e stato di salute |
| `df_raw_items` | tutto ciò che entra, grezzo. **Non si cancella mai** |
| `df_articles` | gli articoli in italiano: bozze, pubblicati, scartati |
| `df_temi` | le raccolte tematiche |
| `df_richieste` | gli articoli commissionati |
| `df_albums`, `df_shows` | discografia e concerti (in gran parte da riempire) |
| `df_run_log` | cosa ha fatto ogni giro e quanto è costato |
| `df_admin_users` | tu |

`df_raw_items` non si svuota di proposito: è la memoria che impedisce di
rielaborare — e ripagare — la stessa notizia. Contiene anche gli scarti,
con il motivo, quindi una decisione si può sempre rivedere.

Convivono con le tabelle `wp_*` del vecchio WordPress. Il piano di
hosting consente **un solo database**, da cui il prefisso `df_`.

---

## 7. Le manopole

In `config.php`:

| Chiave | Cosa fa |
|---|---|
| `soglia_rilevanza` | sotto questo valore l'evento non diventa articolo. **40** per il flusso quotidiano, **50-60** per i recuperi in blocco |
| `max_eta_giorni` | 14. Oltre, l'articolo è archiviato ma non lavorato |
| `dedup_giorni` | 7. Finestra entro cui due titoli simili sono lo stesso fatto |
| `max_eventi_per_giro` | 8. Tetto agli articoli per giro |
| `keywords` | il filtro che tiene bassa la spesa: ciò che non le contiene non arriva mai al modello |
| `modello`, `effort` | `claude-opus-5`, `medium` |
| `cache_ttl` | 900 secondi. La cache si svuota comunque a ogni pubblicazione |
| `base_url` | vuoto: il sito sta nella radice. Era `/v2` prima del trasloco |

La soglia di rilevanza è la manopola che conta: regola insieme il rumore
del sito e il conto di fine mese.

---

## 8. Quando qualcosa non va

**Dove guardare, in ordine:**

1. `/home/bpdefton/deftones/app/logs/<script>.log` — cosa ha fatto lo script
2. **cPanel → Errori** — i fatal di PHP, che nel log dello script **non ci sono**
3. `df_run_log` — l'esito di ogni giro

Il punto 2 è quello che si dimentica. Quando un processo muore di
schianto non fa in tempo a scrivere nel proprio registro: l'unica
traccia resta nell'error_log di Apache.

**Errori già incontrati e risolti** — se tornano, sai dove guardare:

| Sintomo | Causa |
|---|---|
| `MySQL server has gone away` | connessione scaduta durante un'attesa lunga sull'API. Si risolve con `db(true)`, che verifica e riconnette. Le query preparate vanno **ripreparate**: appartengono alla connessione |
| `Unknown column` | una colonna aggiunta dal codice ma non ancora creata. Il file SQL corrispondente non è stato eseguito |
| Sintassi SQL rifiutata | `ADD COLUMN IF NOT EXISTS` è MariaDB. MySQL vuole il controllo in `information_schema` |
| Log fermo dopo una riga | il processo è morto lì. Guarda l'error_log |
| Richiesta appesa in "lavorazione" | si sblocca da sola dopo mezz'ora al giro successivo |
| Il sito mostra roba vecchia | la cache. Svuotala dal pannello |
| Il deploy non cambia niente | hai premuto *Update from Remote* ma non *Deploy* |

---

## 9. Perché certe cose sono così

**Raggruppare prima di scrivere.** Dodici testate coprono lo stesso
concerto. Scrivendo un articolo per fonte il sito diventa illeggibile e
il conto si moltiplica. Il raggruppamento per evento è ciò che rende il
sistema sostenibile — ed è anche la ragione per cui la prima chiamata
non scrive niente.

**La data dell'articolo viene dalla fonte.** È quella del fatto, non
quella in cui l'abbiamo scritto. Su un recupero di arretrato la
differenza è di mesi.

**Le fonti si accreditano per nome.** Google News è il postino, non la
fonte. L'editore vero si legge dal tag `<source>` del feed. Un
aggregatore che non accredita nessuno è precisamente ciò che fa
arrabbiare le testate.

**Il riepilogo tace quando non c'è niente.** Una mail quotidiana che
dice "nessuna novità" smette di essere letta in una settimana, e allora
non serve più nemmeno il giorno in cui ha qualcosa di importante da dire.

**Niente pulsanti di condivisione ufficiali.** Quelli di Meta e X sono
script che tracciano ogni visita anche di chi non condivide. I nostri
sono link: non parte una richiesta finché non li premi. È ciò che tiene
il sito senza cookie e senza banner.

**`media/` sta nella radice, non fra gli assets.** Gli articoli importati
dal vecchio WordPress puntano a `/media/...` con percorso assoluto, perché
è la riscrittura di `/wp-content/uploads/...`: non passa da `base_url`,
quindi la cartella deve stare esattamente lì e le cartelle degli anni
devono essere il suo primo livello — `media/2012/03/foto.jpg`.

Non viene toccata da nessun deploy, ed è l'unica parte del sito che non
sta in git: la sua copia buona è `wp-content/uploads/` dell'archivio
WordPress. Da lì si ricostruisce in qualsiasi momento.

**La sitemap si costruisce da sola.** `/sitemap.xml` è una rotta, non un
file: legge il database a ogni richiesta e finisce in cache come le altre
pagine. Non c'è niente da rigenerare quando pubblichi — l'indirizzo lo
trova Google da `robots.txt`, e la data di ultima modifica gliela dà
`aggiornato_il`. Le categorie e le raccolte ci entrano solo se hanno
almeno un articolo pubblicato dentro.

**Il primo accesso al pannello si chiude da solo.** La pagina che crea
l'utente funziona solo finché la tabella è vuota. Non c'è un file di
setup da ricordarsi di cancellare.

**I link di Google News non sono risolvibili.** Verificato: rispondono
200 con una pagina JavaScript e il payload è protobuf senza URL in
chiaro. Per questo servono i feed diretti delle testate, che danno
indirizzi veri.

---

## 10. Cosa resta

**Pubblicare l'archivio.** 265 bozze valutate e ordinate per rilevanza,
otto raccolte pronte in bozza. Il consiglio è di procedere per raccolta:
pubblichi gli articoli di un dossier, poi il dossier. Comincerei da *Il
coma di Chi Cheng, giorno per giorno*.

**Riempire la discografia.** `df_albums` ha i 13 dischi con date e
identificativi, ma descrizioni e tracklist sono vuote. Il posto giusto
è `scrivi-richieste.php`, una scheda alla volta.

**Le date dei concerti.** `df_shows` è vuota. Servono le chiavi di
Bandsintown e setlist.fm, entrambe gratuite.

**Le immagini 2002-2011.** Perse, a meno che non salti fuori un backup.
I riferimenti però sono conservati sotto `/media/legacy/`: se un giorno
copi lì i file rispettando i percorsi originali, le immagini tornano da
sole senza reimportare nulla.

---

*Ultimo aggiornamento: 29 agosto 2026 — trasloco nella radice.*
