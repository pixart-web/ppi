# Instalação

## Requisitos (confirmados em 3–6 de novembro de 2026)
WordPress 7.1.4, PHP 8.3 (mínimo do plugin: 8.1), MariaDB 11.4 ou MySQL 8, Polylang 3.9.1 (gratuito, GPL). HTTPS em produção. Sem Node nem Docker em produção. Sem plugins pagos, Elementor ou WooCommerce.

## Ambiente local (Docker)
```bash
bin/setup.sh
```
- Site: http://ppi.localhost:8097/ · http://ppi.localhost:8097/en/
- Painel: /wp-admin/ (credenciais geradas em `.credentials.local`, não versionado)
- Emails capturados: http://localhost:8098 (Mailpit; nada sai para a Internet)
- `bin/setup.sh` é idempotente. `bin/setup.sh --reset` só recria os contentores: para apagar os dados, remova também `.data/`.
- Base de dados e uploads ficam em `.data/` (disco do anfitrião), fora do disco virtual do Docker.

## WordPress convencional
1. Instalar WordPress e o plugin Polylang.
2. Enviar `dist/ppi-core.zip` (Plugins → Adicionar) e `dist/ppi-estates.zip` (Aspeto → Temas) e ativar.
3. Definições → Ligações permanentes: «Nome do artigo».
4. Línguas → criar Português (pt_PT, predefinido) e English (en_GB); URL: idioma no diretório, ocultar o predefinido.
5. Com WP-CLI, importar e configurar:
```bash
wp ppi import migration/properties.json
```
```bash
wp ppi import migration/properties.json --apply
```
```bash
wp ppi locations migration/locations.json
```
```bash
wp ppi translate migration/translations-en.json --lang=en
```
```bash
wp eval-file bin/site.php
```
`bin/site.php` lê `/migration/legal/privacy-original.txt`: ajuste o caminho se a pasta não estiver em `/migration`.

## Configuração por ambiente (wp-config.php ou variáveis do alojamento; nunca no repositório)
| Constante | Função |
|---|---|
| `WP_ENVIRONMENT_TYPE` | `production` só na publicação autorizada. Fora de produção: noindex global e emails desviados. |
| `PPI_SMTP_HOST`, `PPI_SMTP_PORT`, `PPI_SMTP_USER`, `PPI_SMTP_PASS`, `PPI_SMTP_SECURE` | Envio SMTP (`tls` ou `ssl`). |
| `PPI_MAIL_FROM` | Remetente autorizado no domínio. |
| `PPI_MAIL_TEST_TO` | Caixa de teste fora de produção. |
| `PPI_DEMO_PASSWORD` | Palavra-passe opcional de pré-visualização. |

Na publicação: `blog_public` = 1 (Definições → Leitura) e `WP_ENVIRONMENT_TYPE=production`. Ver [operacao.md](operacao.md).
