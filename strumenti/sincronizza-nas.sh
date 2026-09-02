#!/bin/bash
#
# Copia l'albero di lavoro sul NAS di prova (Synology DS120j, 192.168.1.9).
#
# Sincronizza quello che c'è sul disco adesso, committato o no: è la
# differenza fra provare una modifica e pubblicarla. Il ciclo diventa
# "modifico, sincronizzo, guardo" senza passare da git né da cPanel, e
# senza mettere online una riga non ancora verificata.
#
# Perché tar e non rsync: su DSM /bin/rsync è setuid root e rifiuta le
# sessioni che non arrivano dal servizio di backup di rete — autenticazione
# riuscita, poi "Permission denied". E il rsync di macOS non è più quello
# vero ma openrsync, che di opzioni ne ha la metà. Con tar non dipendiamo
# né dall'uno né dall'altro, e un albero da un mega viaggia in un secondo.
#
# Uso:  strumenti/sincronizza-nas.sh
#       DEST=/volume1/web/altro strumenti/sincronizza-nas.sh
#
set -euo pipefail

NAS=${NAS:-nas}                       # host di ~/.ssh/config
DEST=${DEST:-/volume1/web/deftones}

cd "$(dirname "$0")/.."

# Quello che non deve mai essere toccato dalla sincronizzazione:
# config.php ha le credenziali del NAS, gli altri tre sono prodotti a
# runtime. Vale sia per la cancellazione qui sotto sia per il tar.
TIENI='! -name config.php ! -name logs ! -name cache ! -name media'

# Prima si fa piazza pulita, poi si estrae. Sovrascrivere e basta
# lascerebbe in giro i file rinominati o eliminati, e passeresti un
# pomeriggio a debugare una vista che sul NAS esiste ancora.
ssh "$NAS" "mkdir -p $DEST && cd $DEST && mkdir -p app web sql && \
            find app web sql -mindepth 1 -maxdepth 1 $TIENI -exec rm -rf {} +"

# COPYFILE_DISABLE e --no-xattrs tolgono i metadati di macOS: il tar del
# NAS non li conosce e stamperebbe una riga di avviso per ogni file.
COPYFILE_DISABLE=1 tar czf - --no-xattrs --exclude '.DS_Store' --exclude 'app/config.php' \
          --exclude 'app/logs' --exclude 'app/cache' --exclude 'web/media' \
          app web sql | ssh "$NAS" "tar xzf - -C $DEST"

# Le cartelle scritte a runtime appartengono al server web, che gira come
# utente "http": dentro una cartella di perseverance non potrebbe creare
# niente, e la cache muta si presenta come "il sito non si aggiorna mai".
# Il chmod si limita ai file di cui siamo proprietari: dentro cache/ e
# logs/ ci scrive Apache come utente "http", e un chmod ricorsivo cieco
# fallirebbe proprio lì — interrompendo la sincronizzazione a un passo
# dalla fine, con i file già copiati e nessun errore che lo spieghi.
ssh "$NAS" "cd $DEST && mkdir -p app/logs app/cache web/media && \
            chmod 777 app/logs app/cache web/media && \
            find app web sql -user \$(id -un) -exec chmod a+rX {} +"

echo "Sincronizzato su $NAS:$DEST"
