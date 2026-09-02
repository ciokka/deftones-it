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
      ↓  copertine.php · ogni 4 ore, gratis
  con la fotografia      da Wikimedia o dal Cover Art Archive
      ↓  TU, dal pannello
  online
```

Ma non è solo un aggregatore. Sopra ci sono l'archivio del vecchio sito
diviso in dossier, la discografia costruita da MusicBrainz, la ricerca a
testo pieno, e uno strumento per commissionare un articolo su un
argomento e farselo scrivere con ricerca sul web.

Niente va online da solo. Ogni cosa nasce in bozza e aspetta la tua
approvazione — è la scelta che tiene il sito tuo invece che di un
programma.

Tre regole che si ritrovano dappertutto, e che spiegano molte
decisioni prese lungo la strada:

- **i fatti si prendono dove sono registrati**, non dalla memoria di un
  modello: le tracklist da MusicBrainz, le date dalle pubblicazioni;
- **un testo senza fonti non si pubblica**, nemmeno se è scritto bene;
- **niente si carica da server di altri** quando la pagina si apre: né
  caratteri, né fotografie, né video. Per questo non serve un banner.

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
│   ├── cron/                  i dieci script
│   ├── lib/                   funzioni condivise
│   ├── views/                 i template
│   ├── cache/                 pagine generate — si svuota da sé
│   └── logs/                  un file per script, si ruota a 2 MB
│
├── public_html/               ciò che il web può vedere
│   ├── index.php              unico punto d'ingresso
│   ├── .htaccess              manda tutto a index.php
│   ├── assets/                CSS, font, pattern, immagini
│   ├── favicon.svg .ico       l'icona del sito, e apple-touch-icon.png
│   ├── robots.txt             cosa i motori possono percorrere
│   └── media/                 immagini del vecchio WordPress, copertine
│                              degli articoli e dei dischi
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

### Le librerie

| File | Cosa contiene |
|---|---|
| `bootstrap.php` | configurazione, database, log, scaricamento, feed, slug, email |
| `web.php` | escaping, indirizzi, cache su file, rendering, copertine, crediti, ricerca |
| `claude.php` | le chiamate al modello, con e senza ricerca sul web |
| `prompts.php` | le istruzioni date al modello, in un posto solo |
| `copertine.php` | Wikimedia Commons, Cover Art Archive, scelta e assegnazione |
| `openverse.php` | le foto libere di Flickr, raggiunte tramite Openverse |
| `icone.php` | le iconcine del pannello |

### I file SQL, nell'ordine in cui sono stati eseguiti

Nessuno di questi viene eseguito da solo: si incollano a mano in
phpMyAdmin. Servono a ricostruire il database da zero, e a capire da
dove viene una colonna.

| File | Cosa aggiunge |
|---|---|
| `schema.sql` | le tabelle di partenza |
| `albums-seed.sql` | i tredici dischi con i loro identificativi MusicBrainz |
| `temi.sql` | `df_temi`, le raccolte tematiche |
| `richieste.sql` | `df_richieste`, gli articoli su commissione |
| `correzioni-01…07.sql` | ritocchi fatti strada facendo |
| `copertine.sql` | le colonne `immagine_*` e la tabella `df_immagini` |
| `ricerca.sql` | l'indice a testo pieno che comprende il corpo |
| `apertura.sql` | `in_apertura`, per fissare una notizia in vetrina |
| `data-foto.sql` | `data_foto`, quando è stata scattata |
| `flickr.sql` | `commons` diventa `riferimento`, e nasce `provenienza` |
| `controllo.sql`, `ispezione.sql` | non modificano niente: servono a guardare |

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

Percorso completo del PHP: `/opt/cpanel/ea-php83/root/usr/bin/php -q`.
Gli script stanno in `/home/bpdefton/deftones/app/cron/`.

### I cinque fissi

Questi restano in tabella. Il redirect è `>/dev/null` **senza** `2>&1`.

`5 */4 * * *`
```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/ingest.php >/dev/null
```

`35 */4 * * *`
```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/enrich.php >/dev/null
```

`50 */4 * * *`
```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/copertine.php >/dev/null
```

`*/30 * * * *`
```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/scrivi-richieste.php --una >/dev/null
```

`15 9 * * *`
```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/riepilogo.php >/dev/null
```

**`>/dev/null` senza `2>&1`**, e non è un dettaglio: l'output normale
sparisce, ma gli errori restano su stderr e cPanel te li manda per
email. Aggiungendo `2>&1` spariscono anche quelli, e un guasto diventa
silenzioso. Perché la mail parta, il campo email in cima alla pagina
Processi Cron dev'essere compilato.

Le copertine girano subito dopo la scrittura, e come le richieste non
costano niente a vuoto: pescano da un catalogo locale, senza toccare né
gli archivi di fotografie né l'IA.

Le richieste girano ogni mezz'ora perché a vuoto non costano niente: lo
script prende un lock, non trova nulla in attesa ed esce. È la differenza
fra ordinare un articolo e averlo entro mezz'ora, o il giorno dopo.

### Gli strumenti da lanciare a mano

Non si programmano. Si crea un processo temporaneo con orario fra due
minuti, si legge il log, **e poi si cancella la riga**. Qui `2>&1` ci
sta, perché quel log lo leggi tu subito dopo.

Raccogliere fotografie da Openverse — dopo un tour, o quando serve altro
materiale. Con `--prova` non scrive niente e dice solo quante ne trova:

```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/copertine.php --raccogli-altre --prova > /home/bpdefton/copertine.log 2>&1
```

Raccogliere fotografie da Wikimedia Commons:

```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/copertine.php --raccogli > /home/bpdefton/copertine.log 2>&1
```

Assegnare copertine in blocco, dopo aver pubblicato molti articoli
d'archivio. Va rilanciato finché il numero non scende sotto il limite:

```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/copertine.php --limite=200 > /home/bpdefton/copertine.log 2>&1
```

Scrivere le schede dei dischi che ne sono ancora prive — **questo costa**,
circa sette centesimi a disco:

```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/dischi.php --schede --tutte > /home/bpdefton/dischi.log 2>&1
```

Tracklist e copertine dei dischi, solo se ne aggiungi uno al seed:

```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/dischi.php --tracklist --copertine > /home/bpdefton/dischi.log 2>&1
```

Gli altri quattro — `importa-wp.php`, `valuta-archivio.php`,
`crea-temi.php`, `recupera-storico.php` — hanno già fatto il loro lavoro
e non dovrebbero servire più. Sono documentati qui sotto, al capitolo 5,
se un giorno servissero.

**Perché nessuno di questi va programmato.** Un `crea-temi.php` lasciato
giornaliero rifarebbe le raccolte ogni notte, con nomi diversi ogni
volta. Un `enrich.php --tutto` quotidiano è una spesa senza tetto, perché
`--tutto` ripete il ciclo finché la coda non è vuota. E una raccolta di
fotografie notturna interrogherebbe duecento volte al mese due archivi
che cambiano due volte all'anno.

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

### copertine.php — illustra
Assegna una copertina agli articoli che non ce l'hanno. Non spende
niente: pesca da un catalogo locale di fotografie con licenza libera.

Tre mestieri distinti. `--raccogli` interroga Wikimedia Commons e
`--raccogli-altre` interroga Openverse, che indicizza le foto libere di
Flickr: tutt'e due riempiono `df_immagini` e vanno fatte di rado, perché
le foto libere dei Deftones non nascono al ritmo delle notizie. Senza
opzioni, assegna.

- `--prova` mostra cosa farebbe, simulando anche la rotazione
- `--limite=N` quanti articoli per giro (40 di suo)
- `--rifai` rimette in gioco anche i già fatti, tranne quelli scelti a mano

La scelta vera sta in `assegnaCopertina()`, nella libreria: la usa anche
il pulsante *pubblica con copertina* del pannello.

### dischi.php — la discografia
Tre mestieri separati, perché hanno costi e rischi diversi.

- `--tracklist` prende da MusicBrainz brani, durate, data ed etichetta.
  Gratis e verificabile
- `--copertine` scarica le copertine dal Cover Art Archive. Gratis
- `--schede` fa scrivere al modello il racconto del disco, cercando sul
  web. **Costa circa 0,07 € a disco.** Ne fa uno per volta; `--tutte` li
  fa tutti

Con `--solo=slug` si lavora su un disco solo, con `--rifai` si rifà.

---

## 6. Il sito

| Indirizzo | Cosa c'è |
|---|---|
| `/` | l'apertura — le tre notizie in evidenza, che avanzano scorrendo — e sotto le ultime diciotto |
| `/notizie` | l'archivio completo, filtrabile per anno e categoria, con *carica altro* |
| `/notizie/{slug}` | l'articolo |
| `/categoria/{cat}`, `/tag/{tag}` | tagli dell'archivio |
| `/raccolte`, `/raccolte/{slug}` | i dossier tematici, in ordine cronologico |
| `/discografia`, `/discografia/{slug}` | i dischi, con tracklist e scheda |
| `/cerca` | ricerca a testo pieno; `/cerca.json` serve i suggerimenti |
| `/privacy` | l'informativa |
| `/feed.xml`, `/sitemap.xml`, `/robots.txt` | per chi legge da fuori |

I vecchi indirizzi di WordPress — `/GG-MM-AAAA/slug` — reindirizzano
all'articolo corrispondente, ma **solo se è pubblicato**: un 301 verso un
404 è peggio di un 404 diretto. È un motivo in più per pubblicare
l'archivio: ogni articolo riaccende i link che puntavano a lui da anni.

**L'apertura** è un carosello di tre notizie che avanzano seguendo lo
scorrimento: resta agganciata in cima per un tratto di pagina, poi si
sgancia e la pagina riprende. Non intercetta niente — il dito e la
rotella muovono sempre la pagina, cambia solo cosa si vede muovere — e
sotto `prefers-reduced-motion` non si attiva affatto. All'articolo ci si
va dal titolo o dal pulsante, non cliccando la diapositiva intera: su un
carosello che avanza scorrendo, un bersaglio grande quanto lo schermo
raccoglie clic che nessuno voleva dare.

Prende di suo le tre notizie più recenti. Dal pannello se ne possono
fissare: quelle fissate vengono prima, le recenti completano.

Fissarne una fa anche un'altra cosa: la sua copertina diventa l'immagine
che si vede quando qualcuno condivide la home. La pagina ne dichiara
quattro, ma Facebook ha smesso di offrire il selettore e prende sempre la
prima — quindi la scelta la si fa dal pannello invece che al momento di
condividere. Senza nessuna fissata resta l'immagine del sito, che non
invecchia mentre una notizia sì.

---

## 7. Il pannello

Sta in `/admin`. Il primo accesso crea l'utente e poi si chiude da solo.

**L'elenco delle bozze** si filtra per parola, anno e categoria, e si
ordina per rilevanza, lunghezza o data. Con le caselle si pubblica o si
scarta in blocco — ma solo una selezione esplicita, mai "tutte quelle che
vedi".

**Nuovo articolo** — `/admin/nuovo` — scrive un pezzo a mano, senza IA: gli stessi campi
della modifica, e in fondo tre modi di finire — *salva come bozza*,
*pubblica*, *pubblica con copertina*. Sono tre intenzioni diverse, non
tre varianti dello stesso pulsante. Un articolo scritto così resta senza
`modello` e senza `uso_token`, ed è giusto che si veda: quelle colonne
sono la traccia di cosa ha generato cosa.

**Foto** — `/admin/foto` — apre il catalogo intero e serve a fare
pulizia. Un clic su una
fotografia la toglie, un altro la rimette. Le scartate non vengono
cancellate: restano lì, così una raccolta futura non le riporta dentro.

Vale la pena passarci una volta con calma. Le fotografie libere dei
Deftones sono grandi — mediana tremila pixel — ma la qualità
*fotografica* varia moltissimo, e nessun filtro automatico la può
giudicare: buio, sfocato e nuca sono cose che si vedono solo guardando.
Mezz'ora spesa lì migliora ogni assegnazione futura.

**Copertina** — `/admin/copertina/{id}` — apre il catalogo delle
fotografie e ne fa scegliere una a mano. Vengono prima quelle del soggetto giusto e quelle usate meno, e si
possono filtrare per autore. Scegliendone una, l'origine diventa
*manuale* e il cron non la cambia più, nemmeno con `--rifai`. Da lì si
può anche far cercare al programma un'altra foto, o togliere la
copertina e rimettere l'articolo in coda.

**Su ogni bozza**: *leggi* apre l'anteprima, *modifica* il modulo,
*copertina* il catalogo. Dall'anteprima si pubblica.

**Pubblica con copertina** pubblica e cerca subito la foto, invece di
aspettare il giro delle copertine che passa ogni quattro ore. Ci mette
qualche secondo perché la scarica davvero.

**Altra copertina** bandisce quella foto dal catalogo — così non ricompare
su un altro pezzo — e rimette l'articolo in coda.

**Modifica** cambia titolo, sommario, corpo, categoria, attendibilità,
tag, fonte e data di pubblicazione. La data non è cosmetica: sposta
l'articolo nell'ordine cronologico e negli archivi per anno.

**Sulle pubblicate**: *in apertura* fissa l'articolo nel carosello,
*ritira* lo rimanda in bozza.

**Richieste** commissiona un articolo su un argomento; **raccolte**
pubblica i dossier; **svuota cache** serve quando hai cambiato qualcosa
fuori dal pannello — con una query diretta, per esempio — e il sito
mostra ancora la versione vecchia.

---

## 8. Le tabelle

| Tabella | Cosa contiene |
|---|---|
| `df_sources` | i feed, con peso e stato di salute |
| `df_raw_items` | tutto ciò che entra, grezzo. **Non si cancella mai** |
| `df_articles` | gli articoli in italiano: bozze, pubblicati, scartati |
| `df_temi` | le raccolte tematiche |
| `df_richieste` | gli articoli commissionati |
| `df_immagini` | il catalogo delle foto libere: riferimento, provenienza, autore, licenza, data di scatto, quante volte è stata usata, e se è stata scartata |
| `df_albums` | la discografia: date, etichette, tracklist, schede |
| `df_shows` | i concerti — ancora da riempire |
| `df_run_log` | cosa ha fatto ogni giro e quanto è costato |
| `df_admin_users` | tu |

`df_raw_items` non si svuota di proposito: è la memoria che impedisce di
rielaborare — e ripagare — la stessa notizia. Contiene anche gli scarti,
con il motivo, quindi una decisione si può sempre rivedere.

Convivono con le tabelle `wp_*` del vecchio WordPress. Il piano di
hosting consente **un solo database**, da cui il prefisso `df_`.

---

## 9. Le manopole

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
| `media_dir` | dove stanno i file caricati sul disco: immagini del vecchio WordPress e copertine scaricate |

La soglia di rilevanza è la manopola che conta: regola insieme il rumore
del sito e il conto di fine mese.

---

## 10. Quando qualcosa non va

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

## 11. Perché certe cose sono così

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

**`information_schema` non si può usare nei file SQL.** phpMyAdmin si
collega come utente cPanel — `bpdefton` — mentre gli script PHP usano
l'utente del database, che è un altro e ha altri permessi. Il primo lì
dentro non può guardare e risponde `#1044`. Nei file SQL si scrivono
quindi `ALTER` e `CREATE` semplici: rilanciarli dà "Duplicate column
name", che è la stessa informazione detta dopo invece che prima. Negli
script PHP il controllo si fa con `SHOW COLUMNS`, che richiede solo il
permesso di leggere la tabella.

**Il sito è senza cookie, e non per caso.** Nessuna pagina pubblica manda
un `Set-Cookie`, e nessuna carica niente da server di altri: i caratteri
sono ospitati qui e non su Google Fonts, le fotografie di Wikimedia sono
copiate sul server e non richiamate da lì, i pulsanti di condivisione
sono collegamenti e non widget. Aprendo una pagina il browser di chi
legge contatta solo deftones.it.

Sono le due cose — font remoti e immagini richiamate da terzi — che di
solito fanno perdere le cause sul GDPR, e la conseguenza pratica è che
**non serve nessun banner**.

I video di YouTube dell'archivio — cento iframe in quarantacinque
articoli — sarebbero stati la terza. Anche nella versione "nocookie" un
iframe contatta Google appena la pagina si apre. Per questo vengono
sostituiti al momento di mostrare la pagina con una facciata: un riquadro
nostro, e il video arriva solo al clic. La sostituzione avviene in
`facciateVideo()` e non nel database, così gli articoli restano come sono
stati importati. Vale la pena non rovinarlo: il giorno che si
aggiunge uno strumento di statistica, un video incorporato o un pulsante
ufficiale di un social, il banner diventa obbligatorio e con lui tutto
quello che si porta dietro.

L'informativa in /privacy resta comunque necessaria, perché i log del
server registrano gli indirizzi IP.

**"Pubblica con copertina" fa il lavoro del cron su un articolo solo.**
Il giro delle copertine passa ogni quattro ore, e un pezzo appena
pubblicato resterebbe spaiato proprio nel momento in cui lo vai a
guardare. Il pulsante scarica la foto subito, quindi ci mette qualche
secondo: per questo è separato da "pubblica" e non lo sostituisce. La
scelta è la stessa, perché è la stessa funzione — `assegnaCopertina()`.

**L'apertura si può fissare.** Il carosello della home prende di suo le
tre notizie più recenti. Dal pannello, sulle pubblicate, l'interruttore
*in apertura* ne fissa una: quelle fissate vengono prima, e le più
recenti completano fino a tre. Se non ne fissi nessuna il comportamento
è quello naturale, quindi la colonna non va gestita — esiste per le
eccezioni.

**Un testo senza fonti non si pubblica.** Gli strumenti che scrivono
cercando sul web — le schede dei dischi e gli articoli su richiesta —
scartano il risultato se il modello non ha consultato nessuna fonte. Non
è una precauzione teorica: quando il limite del tool di ricerca si è
esaurito, il modello ha scritto una spiegazione del perché non poteva
farcela, e quella spiegazione è finita pubblicata come scheda di White
Pony. Un testo che doveva poggiare su fonti e non ne ha nessuna non è un
testo corto: è un'altra cosa.

**MusicBrainz è un registro, ma compilato da volontari.** Per White Pony
dichiara come data d'uscita il 27 aprile 2000, sulla fede di una sola
pubblicazione, mentre sette portano il 20 giugno — che è la data vera.
Per questo la data si sceglie per consenso, e non prendendo la più
vecchia. Stesso criterio per la tracklist: fra le stampe della stessa
data vince quella con meno brani, perché le bonus track si aggiungono a
un album e non lo compongono.

**La copertina porta un contrassegno nell'indirizzo.** Il file nuovo si
scrive sempre sullo stesso percorso — è il nome dell'articolo — quindi
per il browser l'indirizzo non cambia e continua a mostrare quello che ha
già. Sostituendo una copertina si vedeva cambiare il nome dell'autore ma
non la fotografia. Il contrassegno viene da `immagine_cercata_il`: cambia
quando cambia la foto e non un attimo prima, quindi non costringe
nessuno a riscaricarla a ogni visita.

**Le misure dichiarate nell'anteprima social sono quelle vere.** Le foto
di Commons hanno proporzioni tutte diverse e le copertine dei dischi sono
quadrate: dichiarare 1200×675 per tutte era una bugia, e i social usano
quei numeri per disporre l'anteprima prima ancora di scaricare
l'immagine. Si misurano con `getimagesize()` sul file locale — possiamo
permettercelo perché ospitiamo noi ogni copertina, e la pagina finisce
comunque in cache.

**Le copertine non si possono prendere dove capita.** Le foto che
accompagnano gli articoli dei giornali sono di agenzia o dei fotografi
accreditati. Prenderne l'`og:image` è quello che fa quasi ogni
aggregatore, ed è anche il motivo per cui ogni tanto a qualcuno arriva
una richiesta di danni a quattro cifre. Quindi si pesca in tre posti, in
quest'ordine: la copertina del disco se l'articolo parla di un disco, e
una foto libera di Wikimedia Commons. Se non si trova né l'una né
l'altra, l'articolo resta senza copertina: non c'è un ripiego, perché
un'immagine generata riempie lo spazio senza dire niente e accanto a una
foto vera si vede che è un tappabuchi.

Il credito sotto la foto non è una cortesia. Una foto CC BY è libera *a
condizione* che l'autore sia citato: senza quella riga non stiamo usando
una foto libera, stiamo usando una foto altrui. Per questo sta sotto
l'immagine e non in fondo alla pagina.

**Le fotografie vengono da due posti, con lo stesso metro.** Wikimedia
Commons — interrogata direttamente — e Flickr, raggiunta attraverso
Openverse. Stesso modello legale, stesso filtro, stesso catalogo, stessi
crediti.

Su Flickr ce n'è di più perché su Commons arriva solo quello che qualcuno
si prende la briga di trasferire: un quinto delle foto che avevamo veniva
di lì, passata per le mani di un volontario. A Flickr però non si arriva
più direttamente: dal 2024 non rilascia chiavi API agli account
gratuiti. Openverse indicizza le stesse fotografie.

**Openverse va usato con le credenziali**, che sono gratuite. Senza, la
quota è di duecento richieste al giorno contate **per indirizzo IP** — e
su un hosting condiviso quell'indirizzo è lo stesso di centinaia di altri
siti, quindi può risultare esaurita da gente che non sa di averla usata.
Peggio: quando è esaurita la richiesta non fallisce con un errore, resta
appesa finché non scade. Con le credenziali la quota è legata a quelle e
sale a diecimila al giorno. Si mettono in `config.php` come
`openverse_id` e `openverse_secret`; il gettone dura dodici ore e viene
conservato in `cache/`.

I risultati che Openverse restituisce da Wikimedia vengono scartati:
quelli li prendiamo alla fonte, con metadati migliori — compresa la data
di scatto, che Openverse non riporta.

**Le ND non si prendono mai**, e non per prudenza: le copertine vengono
ritagliate a 16:9, e un ritaglio è un'opera derivata. Usarle sarebbe
fuori licenza a prescindere.

**Le NC dipendono da `foto_non_commerciali` in `config.php`**, che oggi
vale `true`: il sito non è commerciale e non ha intenzione di
diventarlo. Se un giorno ci comparisse un banner, un link affiliato o
una raccolta fondi, quella riga va rimessa a `false` — e vanno poi tolte
dal catalogo le foto già entrate:

```sql
SELECT id, licenza, autore FROM df_immagini
 WHERE licenza REGEXP '(^|[^a-z])nc([^a-z]|$)';
```

Nel pannello ogni foto NC porta un bollino, così quel giorno si vede a
colpo d'occhio cosa c'è da togliere.

**Commons viene interrogato di rado, non a ogni articolo.** Le foto
libere dei Deftones non nascono al ritmo delle notizie: `--raccogli`
riempie il catalogo `df_immagini` (162 immagini utilizzabili su 252
viste, alla prima raccolta), e da lì in poi assegnare una copertina è una
query locale. Non dipende dalla rete, non ha limiti di frequenza, e il
catalogo si può curare: dal pannello, *altra copertina* bandisce una foto
brutta e la sostituisce.

**Il campo Restrictions di Commons non parla di copyright.** Su 252 file,
110 portano `personality`: è il diritto all'immagine di chi è ritratto,
che Commons segnala su qualunque foto di persona riconoscibile. Riguarda
l'uso pubblicitario, non quello editoriale, e scartare su quello buttava
via il materiale migliore — intere serie di concerti. `trademarked` e
`insignia` invece fanno scartare: quelli sono marchi.

**La ricerca sta fuori dalla cache.** La chiave della cache è il
percorso, e `/cerca?q=milano` e `/cerca?q=chi` hanno lo stesso percorso:
si servirebbero i risultati a vicenda. È l'unica pagina pubblica esclusa.

Cerca in modalità booleana con `+parola*`: tutte le parole devono
esserci — altrimenti "deftones milano" restituisce mezzo sito — e ognuna
vale anche come prefisso. I caratteri che in quella modalità hanno un
significato vengono tolti prima, o una parentesi storta farebbe fallire
la query. L'indice è su titolo, sommario **e corpo**: senza il corpo,
cercare una parola che compare a metà di un articolo dell'archivio non
trovava niente.

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

## 12. Cosa resta

**Pubblicare l'archivio.** 265 bozze valutate e ordinate per rilevanza,
otto raccolte pronte. Il consiglio è di procedere per raccolta:
pubblichi gli articoli di un dossier, poi il dossier. Comincerei da *Il
coma di Chi Cheng, giorno per giorno*. Con *pubblica con copertina* è
molto più svelto di quanto fosse.

**Le date dei concerti.** `df_shows` è l'unica tabella ancora vuota.
Servono le chiavi di Bandsintown e setlist.fm, entrambe gratuite.

**Le fotografie buone.** Il bacino libero è quello che è: le immagini
sono grandi — mediana tremila pixel — ma la qualità fotografica varia
molto, e nessun filtro la può giudicare. Due strade, nell'ordine: fare
pulizia dal pannello, in `/admin/foto`, una volta con calma; e chiedere
al management o all'etichetta le **foto stampa ufficiali**, che sono
fatte apposta per essere usate dalle testate e di solito si ottengono
gratis con il credito. Se ne arrivano, serve una funzione per caricarle
a mano nel catalogo: non c'è ancora.

**Le immagini 2002-2011.** Perse, a meno che non salti fuori un backup.
I riferimenti però sono conservati sotto `/media/legacy/`: se un giorno
copi lì i file rispettando i percorsi originali, le immagini tornano da
sole senza reimportare nulla.

**Due dettagli della privacy** da verificare su Serverplan: per quanti
giorni conservano i log, e se nel contratto c'è la nomina a responsabile
del trattamento.

**Il foglio di stile.** Ha passato le millecinquecento righe e in una sola
giornata ha prodotto tre collisioni — due di nomi di classe, una di
specificità. Non è sfortuna: è cresciuto oltre la misura in cui si tiene
a mente per intero. Prima o poi va diviso per blocchi con confini netti.

---

*Ultimo aggiornamento: 2 settembre 2026 — carosello guidato dallo
scorrimento, catalogo fotografie con Openverse, articolo a mano e scelta
della copertina dal pannello, favicon, anteprime social con le misure
vere.*
