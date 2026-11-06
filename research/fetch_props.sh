#!/usr/bin/env bash
# Descarrega as fichas públicas listadas em urls-venda.txt (1 pedido/s, sem repetir as já obtidas).
cd "$(dirname "$0")/raw"
UA="Mozilla/5.0 (Macintosh) Chrome/130 PPI-migration-review"
while read -r u; do
  [ -s "props/$u.html" ] && continue
  curl -sL -A "$UA" -o "props/$u.html" "https://www.ppiestateagents.com/$u" || echo "FAIL $u"
  sleep 1
done < urls-venda.txt
echo done $(ls props | wc -l)
