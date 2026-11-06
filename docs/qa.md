# Relatório de QA

Executado a 6 de novembro de 2026 em ambiente local: WordPress 7.1.4, PHP 8.3, MariaDB 11.4, Polylang 3.9.1, Chromium (Playwright 1.64.1). Resultados brutos em `docs/qa/`. Capturas em `docs/capturas/`.

## Resultado
**38 de 38 testes end-to-end a passar** (`cd tools && npx playwright test`).

| # | Critério | Estado | Evidência |
|---|---|---|---|
| 1 | Instalação limpa e reinstalação sem duplicação | Passou | `bin/setup.sh` de raiz e repetido: 0 criados, 150 sem alterações (`qa/reinstalacao.txt`) |
| 2 | Criar, publicar e retirar imóvel | Passou | Teste no painel; retirado responde 410 |
| 3 | Filtros, referência, ordenação numérica, paginação, URL | Passou | Incluindo sem JavaScript e parâmetros inválidos |
| 4 | Favoritos e comparação; retirados | Passou | Persistem após recarregar; anúncio retirado assinalado |
| 5 | Pedido de visita, lead, acesso restrito, email de teste | Passou | Mailpit, destinatário `leads@ppi.test` |
| 6 | Falha de email sem perda nem falsa entrega | Passou | Falha simulada: lead gravado com estado `failed` |
| 7 | Importação repetida, conflitos, imagens | Passou | Mesmas contagens; edição local preservada (`qa/dados.txt`) |
| 8 | PT/EN com equivalência e factos | Passou | Preço igual; anúncio sem tradução não aparece em EN |
| 9 | Galeria, menus e formulários por teclado e toque | Parcial | Teclado e arrasto testados; toque em dispositivo real não |
| 10 | Capturas 390/768/1440; 320 e zoom 200% | Passou | 35 capturas. Reflow verificado a 320 e 720 px (equivale a 1440 com 200%) |
| 11 | Sem imagens quebradas, overflow, erros JS | Passou | Sem avisos no registo PHP nos pedidos verificados |
| 12 | Sem permissão: leads, importação, definições | Passou | Editor recebe 403; leads sem REST |
| 13 | Backup e restauro isolados | Passou | Base de dados igual e 8665 ficheiros (`qa/restauro.txt`) |

**Acessibilidade:** axe sem violações sérias ou críticas em 6 tipos de página. Isto não é uma declaração de conformidade WCAG 2.2 AA.

**Desempenho (laboratório, não são dados de campo):** mobile 390 px, CPU 4x, 1,6 Mbps, 150 ms (`qa/desempenho-laboratorio.txt`).
| Página | LCP | CLS | Transferido |
|---|---|---|---|
| Homepage | 1,73 s | 0,000 | 299 KB |
| Listagem | 2,22 s | 0,000 | 446 KB |
| Ficha | 1,28 s | 0,000 | 183 KB |

Servidor local e sem cache. INP não medido. Repetir em staging real.

**Volume:** 3000 imóveis sintéticos em memória: 1,6 a 3,8 ms por pesquisa, índice de 1 MB. Mede a pesquisa, não a página completa.

## Defeitos encontrados pelos testes e corrigidos
- Envio de formulários com JavaScript ia para URL errado (campo `action` sobrepunha-se a `form.action`).
- Retirar um imóvel em PT deixava a versão EN publicada.
- Reativar o plugin removia as rotas EN das fichas.
- Favoritos mostravam disponibilidade antiga (cache de 60 s).
- Swipe da galeria cancelado pelo arrastar nativo da imagem.
- Contraste insuficiente do antetítulo sobre pedra.

## Não testado
Dispositivos reais, Safari e Firefox; leitor de ecrã; Lighthouse; entrega real de email (só Mailpit); instalação dos ZIPs num alojamento convencional (o mesmo código correu em Docker); Nginx; envio real de CV pelo browser (validação escrita, sem teste automático); mapa, por não haver coordenadas; Google Analytics, por não estar configurado.
