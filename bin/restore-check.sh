#!/usr/bin/env bash
# Restauro em ambiente isolado: base de dados temporária, sem tocar no site em uso. Compara contagens e ficheiros.
set -euo pipefail
cd "$(dirname "$0")/.."
D="${1:?indique a pasta do backup}"
(cd "$D" && shasum -a 256 -c SHA256SUMS)
N="ppi-restore-$$"; T="$(mktemp -d .data/restore.XXXX)"; TDB="$PWD/$T/db"; mkdir -p "$TDB"
trap 'docker rm -f "$N" >/dev/null 2>&1 || true; rm -rf "$T"' EXIT
docker run -d --name "$N" -e MARIADB_ROOT_PASSWORD=restore -e MARIADB_DATABASE=wordpress -v "$TDB:/var/lib/mysql" mariadb:11.4 >/dev/null
for _ in $(seq 1 60); do docker exec "$N" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && break; sleep 2; done
gunzip -c "$D/database.sql.gz" | docker exec -i "$N" mariadb -uroot -prestore wordpress
Q="SELECT post_type,post_status,COUNT(*) FROM wp_posts WHERE post_type IN ('ppi_property','ppi_lead','attachment','page') GROUP BY 1,2 ORDER BY 1,2;"
docker exec "$N" mariadb -uroot -prestore -N wordpress -e "$Q" > "$T/restored.txt"
if docker compose version >/dev/null 2>&1; then COMPOSE="docker compose"; else COMPOSE="docker-compose"; fi
$COMPOSE exec -T db sh -c "mariadb -uwordpress -p\"\$MARIADB_PASSWORD\" -N wordpress -e \"$Q\"" > "$T/live.txt"
diff "$T/live.txt" "$T/restored.txt" && echo "Base de dados restaurada igual à atual: $(wc -l < "$T/restored.txt") grupos."
mkdir "$T/up"; tar -xzf "$D/uploads.tar.gz" -C "$T/up"
A=$(find .data/uploads -type f | wc -l); B=$(find "$T/up/uploads" -type f | wc -l)
echo "Uploads: atual $A · restaurado $B"; [ "$A" = "$B" ]
