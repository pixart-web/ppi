# Manual de gestão

## Imóveis
**Imóveis → Adicionar imóvel.** Título curto e factual, descrição no editor, depois os separadores:
- **Resumo e preço**: referência única, tipo, estado comercial, preço. Sem preço publicado, deixe vazio; se for o caso, marque «Preço sob consulta». Nunca escreva 0.
- **Áreas**: útil, bruta e terreno são campos distintos.
- **Localização**: marque concelho e freguesia na caixa «Localizações». Coordenadas públicas só com autorização; os campos privados nunca chegam ao site.
- **Fotografias e media**: arraste para ordenar; a primeira é a capa. «Ponto focal» define o centro dos recortes.
- **Características**: lista completa e filtros de pesquisa.

**Publicação e estado comercial são independentes.** Reservado continua na listagem com etiqueta. Vendido mantém a página, sem indexação e fora da listagem. Retirado responde «indisponível» (410). Rascunho esconde sem perder dados.

## Revisão de dados
A caixa «Revisão de dados» mostra divergências encontradas na importação. Com conflitos por verificar, o imóvel não passa de «Pendente». Corrija os campos, marque a confirmação e escreva uma nota. A lista de pendentes abre pelo aviso no topo de **Imóveis**.

## Imagens que não são fotografias
Renders, gráficos e plantas têm etiqueta no site. Na importação ficam depois das fotografias reais. Ao adicionar uma imagem deste tipo, peça a marcação ao administrador (meta `_ppi_media_kind`: `render`, `graphic` ou `plan`).

## Destaques
- **Abertura**: Imóveis → Definições → «Imóvel da abertura» (referência). Usa sempre uma fotografia real.
- **Seleção PPI**: em cada imóvel, «Destaque na homepage (ordem)». 1 é a protagonista. Recomendado: 4 a 6.

## Traduções
Na caixa «Idiomas» do imóvel, crie a versão inglesa e traduza título e descrição. Preço, áreas, referência, estado, fotografias e localização são copiados e mantêm-se iguais. Um imóvel sem versão inglesa não aparece em `/en/`. Páginas traduzem-se da mesma forma; menus em Aspeto → Menus, um por idioma.

## Localidades
Imóveis → Localizações define nome e hierarquia. Textos e fotografia são geridos em `migration/locations.json` (`wp ppi locations`). Use fotografias licenciadas e credite o autor.

## Equipa
Imóveis → Definições → Equipa. Só aparecem membros com validado = 1. A lista do site anterior está guardada com validado = 0.

## Contactos recebidos
Menu **Contactos**, visível a administradores e à função «Gestor de contactos»; editores não têm acesso. Cada pedido tem estado, responsável e notas. «Aceite pelo servidor de email» não prova entrega: confirme sempre o painel. Se o envio falhar, o contacto fica guardado e o sistema repete a cada 15 minutos, até 5 tentativas, ou use «Tentar enviar novamente». «Exportar CSV» neutraliza fórmulas.

Um pedido de visita é uma preferência do cliente, nunca uma marcação confirmada.
