#!/usr/bin/env bash
# Backup local: base de dados, uploads e código, com checksums.
set -euo pipefail
cd "$(dirname "$0")/.."
if docker compose version >/dev/null 2>&1; then COMPOSE="docker compose"; else COMPOSE="docker-compose"; fi
D="backups/$(date +%Y%m%d-%H%M%S)"; mkdir -p "$D"
$COMPOSE exec -T db sh -c 'exec mariadb-dump -uwordpress -p"$MARIADB_PASSWORD" --single-transaction wordpress' | gzip > "$D/database.sql.gz"
tar -czf "$D/uploads.tar.gz" -C .data uploads
tar -czf "$D/code.tar.gz" wp-content bin migration/properties.json migration/manifest.json docker-compose.yml .env.example
(cd "$D" && shasum -a 256 ./* > SHA256SUMS)
echo "$D"
