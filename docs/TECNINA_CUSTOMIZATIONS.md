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
