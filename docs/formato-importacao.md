# Formato de importação e migração

## Ficheiros (`migration/`)
| Ficheiro | Conteúdo |
|---|---|
| `properties.json` | Registos normalizados, formato `ppi-properties/1` |
| `manifest.json` | Por anúncio: URL, referência, origem de cada campo, data de recolha, ficheiros, alertas |
| `media-index.json` | Proveniência e SHA-1 de cada imagem descarregada |
| `media-review.json` | Classificação manual de renders, gráficos e plantas |
| `locations.json`, `translations-en.json` | Textos das localidades e traduções |
| `redirects.csv` | Mapa 301/410 individual |

## Campos de um registo
`external_id`\*, `reference`\*, `title`\*, `description`, `source`, `source_url`, `business`, `type`, `subtype`, `typology`, `bedrooms`, `bathrooms`, `condition`, `energy_class`, `year`, `district`, `municipality`, `parish`, `postal_code`, `price`, `price_on_request`, `currency`, `badge`, `area_useful`, `area_gross`, `area_land`, `features[{group,label,value}]`, `photos[]`, `floorplans[]`, `video`, `agent{name,role}`, `location_query`, `commercial_status`, `fetched_at`, `review{conflicts[],warnings[]}`.

Ausente é `null`, nunca 0. `migration/example.json` traz um exemplo fictício, só para teste.

## Comandos
```bash
wp ppi import ficheiro.json
```
Ensaio: calcula tudo e não escreve nada. Opções: `--apply`, `--batch=25`, `--offset=N`, `--resume`, `--max-photos=12` (0 = todas), `--policy=keep-local|overwrite`, `--report=caminho`.

## Regras
- Chave: ID externo. Uma segunda execução não duplica imóveis nem media (URL de origem e SHA-1).
- Edições locais são preservadas; a versão da fonte fica em `_ppi_source_pending`. `--policy=overwrite` só por decisão explícita.
- Referência já usada com outro ID externo: conflito, sem escrita.
- Ausentes da fonte: reportados, nunca eliminados.
- Conflitos materiais: estado Pendente. Os valores originais ficam em `_ppi_source_values`.
- Títulos só em maiúsculas passam a frase normal; o original fica guardado.
- Sem sincronização contínua: exige fonte, regras e credenciais definidas pela PPI.

## Regenerar a partir do site público
```bash
research/fetch_props.sh
```
```bash
python3 -I research/parse_props.py
```
```bash
python3 -I research/fetch_media.py 12
```
A recolha pública é um recurso de proposta. Para produção, peça à PPI uma exportação autorizada do CRM.
