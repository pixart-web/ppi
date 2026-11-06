# PPI Estate Agents — WordPress

Reconstrução editorial e comercial de https://www.ppiestateagents.com/ («Casas com lugar»). Proposta para revisão em ambiente isolado: nada foi publicado e o site atual, o DNS e o alojamento não foram alterados.

- **Site:** http://ppi.localhost:8097/ · **EN:** http://ppi.localhost:8097/en/
- **Painel:** http://ppi.localhost:8097/wp-admin/ (credenciais em `.credentials.local`)
- **Emails de teste:** http://localhost:8098

```bash
bin/setup.sh
```

Num clone novo, descarregue primeiro as fotografias (não estão no repositório) e as dependências de teste:

```bash
python3 -I research/fetch_media.py 12
```
```bash
cd tools && npm install
```

`bin/package.sh` gera os ZIPs instaláveis em `dist/`.

## Estrutura
| Caminho | Conteúdo |
|---|---|
| `wp-content/themes/ppi-estates` | Tema de blocos: apresentação, `theme.json`, templates, fontes locais |
| `wp-content/plugins/ppi-core` | Imóveis, pesquisa, leads, importador, SEO, consentimento. Trocar de tema não apaga dados |
| `migration/` | Dados, manifesto, revisão de media, traduções, localidades, redirects |
| `research/` | Recolha pública e normalização |
| `bin/` | Instalação, conteúdos, backup, restauro, pacotes, testes de dados |
| `tools/` | Playwright, axe, capturas, desempenho (não usados em produção) |
| `dist/` | ZIPs do tema e do plugin, com checksums |
| `.data/` | Base de dados e uploads locais (fora do Docker; não versionar) |

## Decisões de arquitetura
- **Componentes do catálogo no plugin, estilo no tema.** O briefing atribui blocos ao tema, mas pesquisa e leads ao plugin; a marcação dos blocos de catálogo fica no plugin para sobreviver a uma troca de tema. Cabeçalho, rodapé e todo o CSS ficam no tema.
- **Pesquisa sobre índice em cache**, invalidado em cada alteração: 3000 imóveis em menos de 4 ms.
- **Polylang gratuito** (GPL). PT na raiz, preservando URLs; EN em `/en/`. Factos sincronizados pelo plugin.
- **Dependências:** apenas Polylang. Fontes Source Serif 4 e Source Sans 3 (SIL OFL), alojadas localmente.

## Documentação
[Instalação](docs/instalacao.md) · [Manual](docs/manual.md) · [Formato de importação](docs/formato-importacao.md) · [Operação](docs/operacao.md) · [Segurança](docs/seguranca.md) · [QA](docs/qa.md) · [Pendências](docs/pendencias.md) · [Capturas](docs/capturas/)
