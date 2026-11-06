# Segurança e privacidade

- **Leads**: tipo privado, sem REST, pesquisa, sitemap ou exportação nativa. Capacidade própria `ppi_manage_leads`; editores sem acesso.
- **Formulários públicos**: não usam nonce, porque as páginas podem vir de cache. Protegem-se com campo isca, tempo mínimo assinado por HMAC, limite de 6 pedidos por 15 minutos por origem e validação no servidor. Referência e URL do imóvel derivam do ID no servidor.
- **Duplicados**: impressão digital do conteúdo em 10 minutos e token por carregamento.
- **IP**: não é guardado; só um hash temporário com sal, para o limite.
- **CV**: PDF real (extensão, tipo e assinatura), até 2 MB, em `uploads/ppi-private` com nome aleatório e acesso bloqueado por `.htaccess`. **Em Nginx é preciso bloquear essa pasta na configuração do servidor.** Download só por administrador/gestor, com nonce.
- **Painel**: nonce e capacidade em todas as ações; `DISALLOW_FILE_EDIT`.
- **CSV**: fórmulas neutralizadas.
- **Emails**: fora de produção, tudo vai para `PPI_MAIL_TEST_TO`, incluindo emails do WordPress; Cc/Bcc removidos.
- **REST**: lista de utilizadores fechada a anónimos; coordenadas privadas, morada interna e dados de origem não registados.
- **Consentimento**: medição e conteúdos externos só depois da escolha, alterável no rodapé.
- **Retenção**: 730 dias por defeito, a confirmar pelo responsável pelo tratamento.
