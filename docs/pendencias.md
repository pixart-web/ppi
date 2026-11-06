# Pendências e bloqueios

## Cobertura da migração
| Item | Valor |
|---|---|
| Imóveis declarados no site | 152 |
| URLs únicos encontrados e importados | 150 (diferença de 2 por explicar) |
| Publicados | 137 |
| Pendentes por conflito | 13 |
| Imagens na origem | 3295 |
| Imagens importadas | até 12 por imóvel: 1605 ficheiros, 1512 distintos; 115 imóveis têm mais de 12 |
| Plantas | 7 de 7 |
| Traduções EN de anúncios | 11 de 150 (propostas, por rever) |

Dados recolhidos das páginas públicas a 3 de novembro de 2026, sem acesso ao CRM. **É necessária autorização da PPI** para reutilizar conteúdos e fotografias, e o ideal é uma exportação do HCPro. `--max-photos=0`, com `research/fetch_media.py 0`, completa as galerias.

## Decisões da PPI antes de publicar
**13 anúncios em conflito** (caixa «Revisão de dados»; alguns são falsos positivos do detetor, a confirmar manualmente):
| Ref. | Divergência |
|---|---|
| 10456 | Terreno 3440 vs 2383 m²; **preço 695.000 € no texto vs 550.000 € no campo** |
| 10376 | Preço 27.000.000 €: confirmar |
| 10450 | Terreno 1350 vs 1300 m² |
| 9797 A | Terreno 4,3280 ha vs 42.280 m² |
| 10371 | Título «3 quartos», tipologia T2 |
| 10051, 10296 A, 10386 | Áreas de terreno diferentes ou com gralha no texto |
| 9800, 10019 I, 10019 J, 10412 D, 9852 A | Áreas de construção a confirmar |

Sem revisão, estes anúncios respondem 410 nos redirects: **são 13 linhas de `migration/redirects.csv`**.

**Imagens geradas:** várias capas eram renders ou «AI-aided / virtual interior design by PPI», incluindo em propriedades com ruínas (10320, 10314, 10319 A, 10386, 10461, 10355 B). Estão etiquetadas e as fotografias reais passaram para a frente. 10111, 10111A, 10359 e 10447 não têm nenhuma fotografia real nas imagens revistas. **Só foram revistas as capas e as primeiras 8 imagens destes casos: as restantes galerias precisam de revisão humana.** O texto da etiqueta deve ser validado juridicamente.

**A confirmar:** horário e WhatsApp (ocultos); equipa (guardada, oculta até validação; o número de Erik Johansson, 739 043 316, parece invulgar); afirmação «mais de 25 anos» e «Established since 1998» (citadas do site); 132 anúncios têm alertas não bloqueantes; 3 anúncios têm texto em inglês no site PT; percursos de arrendamento e permuta: o catálogo não tem arrendamentos, mantiveram-se os formulários.

**Textos por rever:** Política de Privacidade (preservada, desatualizada face aos novos formulários; EN em falta); Política de Cookies (proposta); retenção de 730 dias; localidades, passos de venda e traduções EN.

## Limitações conhecidas
- Logótipo só em PNG 400×283: pedir vetorial. O favicon é um recorte do selo.
- Fotografias de 1339 px com marca de água grande, mantida como pedido. Pedir originais à PPI para a abertura e a galeria.
- Sem coordenadas na origem: mapa implementado mas inativo, e sem vista de mapa na listagem. Localização mostrada por freguesia.
- `/en/imoveis/` mantém o caminho PT: traduzir o slug exige Polylang Pro ou desenvolvimento.
- Idiomas ES/FR/DE/RU/ZH do site atual são tradução automática do Google, sem conteúdo próprio; não foram recriados.
- Guias: omitidos por não existirem conteúdos revistos.
- Sem sincronização contínua com o CRM.
- Sem resposta automática ao cliente após o formulário.

## Credenciais e serviços externos
SMTP real e caixa de destino; alojamento e domínio de staging; ID do GA4, se desejado; acesso ao CRM HCPro.
