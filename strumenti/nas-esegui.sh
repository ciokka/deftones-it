#!/bin/bash
#
# Lancia uno script dell'applicazione sul NAS di prova.
#
#   strumenti/nas-esegui.sh ingest
#   strumenti/nas-esegui.sh enrich --prova
#   strumenti/nas-esegui.sh copertine --diagnosi
#
# Sono gli stessi cron che in produzione lancia cPanel, ma qui girano
# contro il database di prova: puoi far male quanto vuoi.
#
set -euo pipefail

NAS=${NAS:-nas}
DEST=${DEST:-/volume1/web/deftones}
PHP=${PHP:-/var/packages/PHP8.3/target/usr/local/bin/php83}

[ $# -ge 1 ] || { echo "uso: $(basename "$0") <script> [argomenti]" >&2; exit 2; }
SCRIPT=$1; shift

exec ssh "$NAS" "cd $DEST/app && $PHP -q cron/$SCRIPT.php $*"
