> **Questo file è il registro storico dell'installazione**, passo per
> passo, così com'è stata fatta. Per capire come funziona il sistema
> oggi, cosa fa ogni script e cosa guardare quando qualcosa non va,
> vai a **[GUIDA.md](GUIDA.md)**.

# deftones.it — installazione passo 1 (ingest)

## Struttura sul server

Il codice sta **fuori** da `public_html`, così `config.php` con le credenziali
non è raggiungibile dal web nemmeno per sbaglio:

```
/home/bpdefton/
├── deftones/
│   └── app/               ← trascina qui la cartella app/ così com'è
│       ├── config.php     ← da creare a partire da config.example.php
│       ├── logs/          ← si crea da sola al primo giro
│       ├── lib/bootstrap.php
│       └── cron/ingest.php
└── public_html/           ← per ora resta il vecchio sito
```

Il percorso completo di riferimento, quello che va nel cron, è quindi:

```
/home/bpdefton/deftones/app/cron/ingest.php
```

## 1. Database

phpMyAdmin → database `bpdefton_base` → tab **SQL** → incolla `sql/schema.sql`.
Crea 7 tabelle con prefisso `df_`. Le `wp_*` non vengono toccate.

## 2. Codice

Trascina via FTP la cartella `app/` dentro `/home/bpdefton/deftones/`,
mantenendola come cartella (non svuotarla: il percorso finale ha `app` in mezzo).
Poi rinomina `config.example.php` in `config.php` e compila:

- `db.pass` → la password MySQL
- `cron_token` → una stringa a caso

`anthropic_key` lascialo vuoto: serve dal passo 2.

## 3. Primo giro — cron "una tantum"

Senza terminale il primo giro si lancia con un cron programmato fra pochi
minuti, che poi trasformerai in quello definitivo.

cPanel → **Processi Cron**. In alto c'è il campo email: mettici il tuo
indirizzo, così per questa prima volta ricevi l'output anche per posta.
Poi compila il modulo guardando l'orologio — scegli un minuto **5 minuti
avanti** rispetto a adesso (esempio: se sono le 22:41, metti 46 e 22):

| Campo | Valore |
|---|---|
| Minuto | `46` ← il minuto scelto |
| Ora | `22` ← l'ora corrente |
| Giorno | `*` |
| Mese | `*` |
| Giorno della settimana | `*` |
| Comando | `/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/ingest.php` |

**Niente `>/dev/null` per questa volta**: vuoi vedere l'output.
Clicca *Aggiungi nuovo processo Cron* e aspetta.

### Come leggere il risultato

Due strade, usa quella che ti torna prima:

- **email** — cPanel ti manda l'output all'indirizzo del modulo
- **file di log** — cPanel → *Gestione file* →
  `/home/bpdefton/deftones/app/logs/ingest.log` → *Visualizza*

La prima riga dice `PHP 8.3.x (cli) — /opt/cpanel/ea-php83/...`.
Serve a verificare due cose in un colpo solo: che il cron parta davvero e
che stia usando la versione giusta.

### Se non succede niente

Nessuna mail e nessun file di log = il percorso del binario PHP è
sbagliato. *Modifica* il cron e usa il comando senza percorso:

```
php -q /home/bpdefton/deftones/app/cron/ingest.php
```

Funziona di sicuro — è la stessa forma del cron che hai già attivo per il
plugin SSL. L'unico svantaggio è che usa la versione PHP di sistema invece
di quella che hai scelto; la prima riga del log te lo conferma.

## 4. Cron definitivo

Quando il primo giro è andato a buon fine, *Modifica* lo stesso cron:

| Campo | Valore |
|---|---|
| Minuto | `5` |
| Ora | `*/4` |
| Giorno | `*` |
| Mese | `*` |
| Giorno della settimana | `*` |
| Comando | `/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/ingest.php >/dev/null 2>&1` |

Gira alle 00:05, 04:05, 08:05 e così via. Il minuto 5 invece di 0 evita
l'ingorgo di inizio ora, quando su un hosting condiviso partono tutti i
cron insieme.

Adesso `>/dev/null 2>&1` ci va: il log su file resta, e non ti ritrovi sei
mail al giorno. Se vuoi essere avvisato solo quando qualcosa va storto,
scrivi `>/dev/null` senza `2>&1`: ricevi la mail solo se il PHP produce
un errore.

## 5. Cosa guardare dopo qualche giorno

```sql
-- quanto produce ogni fonte, e quanto di quello è utile
SELECT s.nome,
       COUNT(*) AS totale,
       SUM(r.stato = 'nuovo')            AS utili,
       SUM(r.stato = 'duplicato')        AS duplicati,
       SUM(r.stato = 'scartato_keyword') AS fuori_tema
FROM df_raw_items r JOIN df_sources s ON s.id = r.source_id
GROUP BY s.nome ORDER BY utili DESC;

-- i feed che stanno dando problemi
SELECT nome, ultimo_esito, errori_consecutivi, ultimo_fetch
FROM df_sources WHERE errori_consecutivi > 0;

-- cosa è passato il filtro: è questo che finirà al modello
SELECT visto_il, titolo FROM df_raw_items
WHERE stato = 'nuovo' ORDER BY id DESC LIMIT 40;
```

L'ultima query è quella che conta: se la lista è piena di roba pertinente
si passa al modello, se è piena di spazzatura si stringe prima il filtro.
Ogni riga di spazzatura eliminata adesso è denaro non speso dopo.

---

# Passo 2 — enrich (raggruppa e scrive)

## File da caricare

In `/home/bpdefton/deftones/app/`:

- `lib/bootstrap.php` (**sovrascrivi**, ha due funzioni nuove)
- `lib/claude.php` (nuovo)
- `lib/prompts.php` (nuovo)
- `cron/enrich.php` (nuovo)

## Righe da aggiungere a config.php

Trova `'anthropic_key' => '',` e sostituisci quel blocco con:

```php
    'anthropic_key' => 'sk-ant-LA-TUA-CHIAVE',

    'modello' => 'claude-opus-5',
    'effort'  => 'medium',

    'soglia_rilevanza'    => 40,
    'max_item_per_giro'   => 60,
    'max_eventi_per_giro' => 8,
```

## Prima esecuzione: modalità prova

`--prova` fa **solo il raggruppamento**: una chiamata sola, nessuna scrittura
sul database, costo circa 10 centesimi. Serve a vedere se il modello capisce
davvero quali notizie parlano dello stesso fatto, prima di pagare per gli
articoli.

Cron una tantum, minuto scelto qualche minuto avanti:

```
/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/enrich.php --prova > /home/bpdefton/enrich-test.log 2>&1
```

Nel log vedrai una riga per evento con rilevanza, attendibilità e numero di
fonti fuse. Le dodici recensioni del concerto di Outbreak devono comparire
come **un evento solo**.

## Giro completo

Tolto `--prova`, scrive le bozze in `df_articles` con `stato = 'draft'`.
Nessuna finisce online da sola.

```sql
SELECT rilevanza, attendibilita, categoria, titolo_it, sommario_it,
       fonte_nome, uso_token
FROM df_articles WHERE stato = 'draft'
ORDER BY rilevanza DESC;
```

## Cron definitivo

Mezz'ora dopo l'ingest, così lavora su roba appena raccolta:

| Campo | Valore |
|---|---|
| Minuto | `35` |
| Ora | `*/4` |
| Comando | `/opt/cpanel/ea-php83/root/usr/bin/php -q /home/bpdefton/deftones/app/cron/enrich.php >/dev/null` |

Nota il `>/dev/null` **senza** `2>&1`: l'output normale viene buttato, ma gli
errori veri (chiave scaduta, credito esaurito, API irraggiungibile) restano su
stderr e cPanel te li manda per email. Silenzio quando funziona, una mail
quando si rompe. Ricordati di compilare il campo email in cima alla pagina
Processi Cron, altrimenti la mail non parte.

## Le due manopole

- **`soglia_rilevanza`** (0-100) — sotto questo valore l'evento viene archiviato
  senza spendere una chiamata. A 40 pubblichi anche le recensioni live estere;
  a 60 solo le cose che contano davvero. È la manopola che regola insieme il
  rumore del sito e il conto di fine mese.
- **`effort`** — quanto il modello ragiona prima di rispondere. `low` costa
  meno ed è più sbrigativo, `medium` è l'equilibrio, `high` è il default
  dell'API. Cambiala solo dopo aver letto qualche articolo prodotto.

---

# Passo 3 — il sito

## Dove va cosa

Il sito nuovo gira in una **sottocartella**, così il vecchio WordPress
resta intatto finché non decidi tu.

```
/home/bpdefton/
├── deftones/app/
│   ├── admin.php          ← nuovo
│   ├── views/             ← nuova cartella, 8 file
│   ├── cache/             ← si crea da sola
│   └── lib/web.php        ← nuovo
└── public_html/
    ├── (il vecchio WordPress, non toccarlo)
    └── v2/                ← nuova cartella
        ├── index.php
        ├── .htaccess
        └── assets/stile.css
```

Dal Mac: il contenuto di `app/` va in `/home/bpdefton/deftones/app/`,
il contenuto di `web/` va in `/home/bpdefton/public_html/v2/`.

**Attenzione al file `.htaccess`**: FileZilla nasconde i file che iniziano
con un punto. Menu *Server → Forza visualizzazione file nascosti*.

## Righe da aggiungere a config.php

```php
    'site_name' => 'deftones.it',
    'site_url'  => 'https://www.deftones.it',
    'base_url'  => '/v2',
    'cache_ttl' => 900,
```

## Primo accesso

Apri **https://www.deftones.it/v2/admin/**

Non esistendo ancora nessun utente, la pagina ti chiede di crearne uno.
Scegli una password vera, di quelle lunghe. Appena l'utente esiste **quella
pagina si disattiva da sola**: non resta una porta aperta se ti dimentichi
di chiuderla.

Poi vedrai le bozze, con Pubblica e Scarta. Pubblicane una e apri
**https://www.deftones.it/v2/** per vederla online.

## Il trasloco nella radice — fatto il 29/08/2026

1. Backup completo da cPanel (cartella home + database)
2. Vecchi file di WordPress spostati fuori da `public_html`,
   tenendo `media/`, `.well-known/` e `cgi-bin/`
3. `.cpanel.yml` distribuisce in `public_html/` invece che in `public_html/v2/`
4. In `config.php`, `'base_url' => ''`
5. MultiPHP Manager: dominio riportato a ea-php83, perché il deploy
   sovrascrive `.htaccess` e con esso il gestore PHP scritto da cPanel
6. Cache svuotata
7. `public_html/v2/` eliminata — i vecchi link `/v2/...` sono
   reindirizzati dalla radice

---

# Distribuzione — come si aggiorna il sito

Dal 28/08/2026 il sito si aggiorna da git. Niente più file scelti a mano
in FileZilla: era la fonte di metà dei problemi avuti in fase di
installazione ("ho caricato index.php ma non web.php").

## Il ciclo

1. Le modifiche vengono committate e spinte su `github.com/ciokka/deftones-it`
2. cPanel → **Git™ Version Control** → repository `deftones-it` → *Pull or Deploy*
3. **Update from Remote** — il server scarica i commit nuovi
4. **Deploy HEAD Commit** — `.cpanel.yml` mette ogni file al suo posto

Il pannello mostra data e SHA dell'ultimo deploy: se coincide con HEAD,
online c'è l'ultima versione.

## Cosa fa il deploy

`.cpanel.yml` nella radice del repository:

| Da | A |
|---|---|
| `app/` | `/home/bpdefton/deftones/app/` |
| `web/` | `/home/bpdefton/public_html/v2/` |

e in fondo svuota `app/cache/`, altrimenti continueresti a vedere le
pagine vecchie anche con i file nuovi.

## Cosa NON viene toccato

- **`config.php`** — non è nel repository (`.gitignore`). Password del
  database, chiave API e ID del workspace restano solo sul server, e
  nessun deploy può sovrascriverli o cancellarli.
- **`app/logs/`** e **`app/cache/`** — prodotti a runtime, non sorgenti.

Se un giorno aggiungessimo una voce nuova a `config.example.php`, quella
va copiata a mano nel tuo `config.php`: è l'unico passaggio manuale
rimasto, ed è voluto.

## Se un deploy rompe qualcosa

Il repository sul server è un clone completo, quindi la versione
precedente c'è. Dal Mac:

```
git revert HEAD          # annulla l'ultimo commit creandone uno nuovo
git push
```

poi *Update from Remote* e *Deploy* di nuovo. Meglio di `reset --hard`:
la cronologia resta leggibile e si vede cosa è stato annullato e perché.

## Nota sulla visibilità del repository

Il repository è **pubblico** perché il piano di hosting non ha le chiavi
SSH e cPanel può clonare solo via HTTPS. Non è un compromesso: nel
repository non c'è nulla di riservato, ed è verificato — nessuna chiave
API, nessuna password, nessun nome utente reale del database.
