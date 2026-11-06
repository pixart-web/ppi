#!/usr/bin/env bash
# ZIPs instaláveis do tema e do plugin, com checksums. Sem segredos nem dados.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist; rm -f dist/*.zip dist/SHA256SUMS
(cd wp-content/themes && zip -qr ../../dist/ppi-estates.zip ppi-estates -x '*.DS_Store')
(cd wp-content/plugins && zip -qr ../../dist/ppi-core.zip ppi-core -x '*.DS_Store')
(cd dist && shasum -a 256 ./*.zip > SHA256SUMS && cat SHA256SUMS)
