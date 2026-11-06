#!/usr/bin/env bash
# Instalação local reproduzível da proposta PPI (idempotente: pode ser repetida sem duplicar dados).
# Uso: bin/setup.sh            instala se necessário, importa e configura (preserva edições locais)
#      bin/setup.sh --reset    apaga a base de dados local e reinstala de raiz
#      PPI_MAX_PHOTOS=0 bin/setup.sh   importa todas as fotografias disponíveis localmente
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
set -a; . ./.env; set +a
if docker compose version >/dev/null 2>&1; then COMPOSE="docker compose"; else COMPOSE="docker-compose"; fi
DOMAIN="${PPI_DOMAIN:-ppi.localhost}"
PORT="${WP_PORT:-8097}"
URL="http://${DOMAIN}:${PORT}"

if [ "${1:-}" = "--reset" ]; then $COMPOSE down -v --remove-orphans; fi
$COMPOSE up -d db wordpress mailpit

echo "A aguardar o servidor web em ${URL} ..."
for _ in $(seq 1 60); do curl -s -o /dev/null "http://127.0.0.1:${PORT}/wp-login.php" && break; sleep 2; done

wp() { $COMPOSE run --rm -T cli wp "$@"; }

if ! wp core is-installed >/dev/null 2>&1; then
  ADMIN_PASS="$(openssl rand -hex 12)"
  wp core install --url="${URL}" --title="PPI Estate Agents" --admin_user=ppi-admin \
    --admin_password="${ADMIN_PASS}" --admin_email=admin@ppi.test --skip-email
  printf 'URL=%s/wp-admin/\nUSER=ppi-admin\nPASS=%s\n' "${URL}" "${ADMIN_PASS}" > .credentials.local
  chmod 600 .credentials.local
  echo "Credenciais locais em .credentials.local (não versionar)."
fi

for loc in pt_PT en_GB; do wp language core install "$loc" >/dev/null 2>&1 || echo "Aviso: pacote $loc indisponível."; done
wp plugin delete akismet hello >/dev/null 2>&1 || true
# Polylang (gratuito, GPL): solução multilingue madura — wordpress.org/plugins/polylang
wp plugin is-installed polylang || wp plugin install polylang --version=3.9.1
wp plugin activate polylang
wp theme activate ppi-estates
wp plugin activate ppi-core
for t in twentytwentythree twentytwentyfour twentytwentyfive; do wp theme delete "$t" >/dev/null 2>&1 || true; done
wp rewrite structure '/%postname%/' >/dev/null
wp option update timezone_string 'Europe/Lisbon' >/dev/null
wp option update WPLANG 'pt_PT' >/dev/null || true
wp option update blog_public 0 >/dev/null   # staging: não indexar
wp option update blogdescription 'Imóveis no Algarve Ocidental' >/dev/null

wp eval-file /opt/ppi-bin/polylang.php

# Imóveis: ensaio primeiro (nada gravado), depois aplicação em lotes com retoma.
wp ppi import /migration/properties.json --max-photos="${PPI_MAX_PHOTOS:-12}" --batch=0 | tail -3
wp ppi import /migration/properties.json --apply --max-photos="${PPI_MAX_PHOTOS:-12}" --batch=25
wp ppi locations /migration/locations.json
wp ppi translate /migration/translations-en.json --lang=en
wp eval-file /opt/ppi-bin/site.php
wp ppi redirects --file=/migration/redirects.csv
wp rewrite flush >/dev/null
wp ppi status

echo
echo "Site:    ${URL}/   ·   ${URL}/en/"
echo "Painel:  ${URL}/wp-admin/ (credenciais em .credentials.local)"
echo "Emails:  http://localhost:${MAILPIT_PORT:-8098} (Mailpit — nada sai para a Internet)"
