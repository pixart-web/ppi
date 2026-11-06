# Backup, restauro, publicação e rollback

## Backup e restauro
```bash
bin/backup.sh
```
Cria em `backups/<data>/` a base de dados, os uploads e o código, com checksums.
```bash
bin/restore-check.sh backups/<data>
```
Restaura numa base de dados isolada e compara contagens, sem tocar no ambiente em uso. Em produção, use o backup do alojamento mais um `mysqldump` diário e cópia de `wp-content/uploads`, incluindo `ppi-private`, para fora do servidor.

## Checklist de publicação (só com autorização da PPI)
1. Resolver [pendencias.md](pendencias.md).
2. Backup do site atual e TTL de DNS baixo.
3. SMTP real com caixa de teste controlada; confirmar a receção na caixa.
4. `WP_ENVIRONMENT_TYPE=production`, `blog_public=1`, remover `PPI_DEMO_PASSWORD`.
5. Aplicar `migration/redirects.csv` e testar uma amostra.
6. Trocar o domínio com `wp search-replace` e `wp ppi reindex`.
7. Testar formulários, sitemap, hreflang e Search Console.

## Rollback
Repor o DNS anterior ou o backup. Os contactos recebidos entretanto ficam no painel: exporte-os antes de restaurar.

## Rotina
Cron do WordPress: `ppi_retry_notifications` (15 min) e `ppi_daily_maintenance` (retenção, por defeito 730 dias). Em alojamento com pouco tráfego, configure um cron real. Dados pessoais: Ferramentas → Exportar/Apagar dados pessoais. Com cache de página, exclua `admin-post.php`; os formulários não dependem de nonces, ver [seguranca.md](seguranca.md).
