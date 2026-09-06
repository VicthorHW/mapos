# Customizações TecNina

Inventário das diferenças mantidas pela fork para facilitar comparação e
reaplicação após atualizações do MapOS upstream.

## Integração WhatsApp — Fase 3

### Arquivos upstream alterados

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `application/.env.example` | Documentar o token interno e TTL do claim | Evitar configuração implícita ou secret versionado | Documentação separada não informa novas instalações durante o setup |
| `composer.json` | Incluir o teste estrutural na suíte padrão | Impedir regressão silenciosa no CI | Executar manualmente; rejeitado por ser fácil esquecer |
| `tools/device-credential/post-deploy.sh` | Encadear a outbox no lifecycle já configurado | Garantir atualização automática da instalação existente | Alteração manual imediata no Coolify; mantido apenas como ponte de compatibilidade |
| `docker/docker-compose.yml` | Permitir criação de trigger com binary logging no MySQL 8.4 | Evitar conceder privilégio global `SUPER` ao usuário do MapOS | Criar a trigger como root manualmente; rejeitado por não ser repetível no deploy |

Nenhum controller ou model existente do MapOS foi alterado. A captura de todas
as mudanças de status é feita pela trigger MySQL instalada separadamente.

### Arquivos novos TecNina

- `application/controllers/Tecnina_integration_setup.php`
- `application/controllers/api/bot/Health.php`
- `application/controllers/api/bot/Outbox.php`
- `application/libraries/Tecnina_bot_auth.php`
- `application/models/Tecnina_outbox_model.php`
- `tools/tecnina-integration/install.php`
- `tools/tecnina-integration/post-deploy.sh`
- `tools/tecnina-post-deploy.sh`
- `tests/TecninaOutboxTest.php`
- `docs/TECNINA_OUTBOX.md`

## Regras de manutenção

- manter o Gateway sem acesso direto ao banco do MapOS;
- não inserir chamadas ao WhatsApp nos controllers de OS;
- executar o instalador com `--verify-only` depois de atualizar o upstream;
- executar testes de contrato antes de habilitar o dispatcher;
- registrar nesta lista qualquer novo arquivo upstream alterado.

## Integração WhatsApp — Fase 4

### Arquivos upstream alterados

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `application/config/routes.php` | Publicar a URL estável `/api/bot/integration-context/{os_id}` | Manter o contrato do `MapOSAdapter` independente do nome de classe do CodeIgniter | Usar underscore na URL; rejeitado por vazar detalhe interno no contrato |
| `composer.json` | Executar o teste do contexto na suíte padrão | Evitar regressão da whitelist e da normalização | Execução manual; rejeitada por ser fácil esquecer |

Nenhum controller ou model existente de OS/cliente foi alterado. O endpoint
novo consulta somente uma whitelist explícita e não reutiliza os métodos
administrativos que fazem `SELECT *`. Números legados sem nono dígito são
rejeitados no envio para evitar inferência que possa alcançar outro titular.

### Arquivos novos TecNina

- `application/controllers/api/bot/Integration_context.php`
- `application/libraries/Tecnina_phone.php`
- `application/models/Tecnina_integration_context_model.php`
- `tests/TecninaIntegrationContextTest.php`

## Integração WhatsApp — Fase 5

### Arquivos upstream alterados

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `application/.env.example` | Documentar `TECNINA_BOT_BASE_URL` | Configurar o cliente interno sem deixar URL implícita | Colocar a URL na view; rejeitado por expor detalhe operacional ao navegador |
| `application/views/tema/topo.php` | Incluir Configurações → WhatsApp | Tornar o painel administrativo acessível ao operador com `cSistema` | Link externo direto ao Gateway; rejeitado porque exporia o token e quebraria a separação de responsabilidades |

### Arquivos novos TecNina

- `application/controllers/Tecnina_whatsapp.php`
- `application/libraries/Tecnina_bot_gateway.php`
- `application/views/tecnina_whatsapp/index.php`

O painel não acessa o banco do Gateway. Cada requisição é autenticada no
servidor MapOS e encaminhada ao contrato `/admin/*`; o token nunca é enviado ao
navegador. O Gateway mascara JIDs/telefones nas listagens e não retorna corpos
de mensagens nos logs operacionais.

## Integração WhatsApp — Fase 7

### Arquivos upstream alterados

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `application/config/routes.php` | Publicar `GET /api/bot/client/by-phone` | O `MapOSAdapter` precisa identificar com exatidão um cadastro sem usar a API administrativa genérica | Fazer SQL no Gateway; rejeitado porque quebraria a separação entre os bancos |
| `composer.json` | Executar o teste de contrato da consulta | Preservar a whitelist e o comportamento de ambiguidade após atualizações | Teste manual; rejeitado por ser fácil esquecer |

### Arquivos novos TecNina

- `application/controllers/api/bot/Client_by_phone.php`
- `application/models/Tecnina_client_lookup_model.php`
- `tests/TecninaClientByPhoneTest.php`

O endpoint aceita apenas a identificação normalizada e responde `none`,
`unique` com `client_id`, ou `ambiguous`. Ele nunca devolve nome, CPF, e-mail,
endereço, senha, hash ou telefone. A consulta é identificação operacional, não
autenticação do cliente.

## Integração WhatsApp — Fase 8 (revisão de intake)

### Arquivos upstream alterados

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `composer.json` | Executar o teste estrutural do painel de revisão | Evitar regressão na autorização, whitelist e proxy server-side | Teste manual; rejeitado por ser fácil esquecer |

### Arquivos TecNina atualizados

- `application/controllers/Tecnina_whatsapp.php`
- `application/views/tecnina_whatsapp/index.php`
- `tests/TecninaIntakeReviewPanelTest.php`

A aba lista somente drafts acionáveis e permite revisar, rejeitar ou aprovar. O navegador
nunca recebe o token do Gateway nem o JID. O ID do operador é obtido da sessão
MapOS, e não de dados enviados pelo formulário. A gravação usa
`review_version` para rejeitar edições concorrentes.

### Fase 8B — aprovação e criação transacional

Arquivos novos TecNina:

- `application/controllers/api/bot/Intake_approval.php`
- `application/models/Tecnina_intake_approval_model.php`
- `tests/TecninaIntakeApprovalTest.php`

Arquivos TecNina atualizados:

- `application/controllers/Tecnina_integration_setup.php`
- `application/controllers/Tecnina_whatsapp.php`
- `application/libraries/Tecnina_bot_gateway.php`
- `application/views/tecnina_whatsapp/index.php`
- `tools/tecnina-integration/install.php`
- `tests/TecninaIntakeReviewPanelTest.php`

Arquivo upstream alterado:

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `application/config/routes.php` | Publicar o endpoint privado de aprovação | Manter o Gateway desacoplado do banco MapOS | SQL direto pelo Gateway; rejeitado por segurança e compatibilidade |
| `composer.json` | Incluir o teste de contrato no conjunto padrão | Detectar regressões de segurança e idempotência | Teste manual; rejeitado por não ser repetível |

A aprovação cria cliente, quando solicitado, e OS na mesma transação MySQL. Uma
tabela própria, instalada de forma idempotente e respeitando `DB_PREFIX`, impede
que reenvios criem outra OS. A correspondência por telefone é refeita no MapOS
imediatamente antes da gravação. Duplicidades exigem decisão explícita. A OS é
criada com `credencial_tipo = nao_informada`, sem PIN, senha ou desenho, para
que a credencial seja coletada somente na triagem física.

O contrato da aprovação também transporta somente a data ISO de criação do
pré-atendimento, validada pelo endpoint privado. A OS resultante recebe
`dataInicial` nessa data e `dataFinal` em sete dias, eliminando datas nulas que
podiam ser apresentadas pelo MapOS como valores inválidos antigos.

## Integração WhatsApp — Fase 8.1 (painel logístico)

### Arquivo upstream alterado

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `composer.json` | Incluir o teste estrutural do painel logístico na suíte padrão | Detectar regressões de autorização, whitelist e privacidade | Execução manual; rejeitada por não ser repetível |

### Arquivos TecNina atualizados

- `application/controllers/Tecnina_whatsapp.php`
- `application/libraries/Tecnina_bot_gateway.php`
- `application/views/tecnina_whatsapp/index.php`
- `tests/TecninaLogisticsPanelTest.php`

O MapOS atua somente como interface e proxy autenticado para a Admin API do
Gateway. Zona, rota, capacidade, perfil e appointment permanecem no banco
`tecnina_bot`. O operador vem da sessão `cSistema`; o navegador não recebe o
token interno, JID, coordenadas exatas ou acesso SQL ao Gateway. Nenhum status
logístico ou coluna de agenda foi acrescentado à OS.

## Fase 8.2 — observabilidade de fluxos e carregamento do painel

Arquivos TecNina atualizados:

- `application/controllers/Tecnina_whatsapp.php`
- `application/views/tecnina_whatsapp/index.php`
- `application/controllers/api/bot/Intake_approval.php`
- `application/models/Tecnina_intake_approval_model.php`

O painel carrega inicialmente apenas visão geral e Conversas. As consultas de
Pré-atendimentos, Logística, Fluxos, Fila, Logs, Regras, Templates e Configuração
são feitas ao abrir cada aba; leituras recebem apenas uma nova tentativa breve
para acomodar o aquecimento do Gateway após deploy. Nenhuma ação de escrita é
repetida automaticamente.
