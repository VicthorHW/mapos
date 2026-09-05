# Outbox MapOS → TecNina Bot

A Fase 3 publica mudanças de status de OS sem depender do caminho que realizou
o `UPDATE`. Painel, faturamento, APIs e Área do Cliente passam pela mesma
trigger do MySQL.

## Instalação

Configure no MapOS:

```dotenv
API_ENABLED=true
MAPOS_BOT_TOKEN=USE_O_MESMO_TOKEN_DO_GATEWAY_COM_32_OU_MAIS_CARACTERES
MAPOS_BOT_CLAIM_TTL_SECONDS=120
```

No Coolify, substitua o comando pós-deploy anterior pelo orquestrador:

```sh
sh /var/www/html/tools/tecnina-post-deploy.sh
```

Ele executa tanto a instalação da credencial de aparelho quanto a instalação
idempotente da outbox. Para apenas verificar:

```sh
sh /var/www/html/tools/tecnina-post-deploy.sh --verify-only
```

O instalador:

- resolve o `DB_PREFIX` efetivo;
- valida a tabela e as colunas da OS;
- verifica o privilégio MySQL `TRIGGER` antes de criar a trigger;
- cria ou repara colunas e índices ausentes da outbox;
- não remove dados;
- aceita execuções repetidas;
- rejeita uma trigger homônima com definição conflitante.

## Contrato interno

Todos os endpoints exigem `Authorization: Bearer <MAPOS_BOT_TOKEN>` e só ficam
disponíveis com `API_ENABLED=true`.

```text
GET  /api/bot/health
POST /api/bot/outbox/claim
POST /api/bot/outbox/ack
```

Claim:

```json
{"batch_size": 25}
```

ACK:

```json
{
  "claim_token": "UUID_DO_CLAIM",
  "event_ids": ["UUID_DO_EVENTO"]
}
```

O payload de evento possui somente `event_id`, `event_type`, `os_id`,
`client_id`, `old_status`, `new_status` e `created_at`. Não inclui nome,
telefone, CPF, endereço, credencial do aparelho, anexos ou secrets.

## Garantias

- status inalterado não gera evento;
- mudança de status gera exatamente um evento por `UPDATE`;
- claim expirado pode ser recuperado;
- ACK repetido com o mesmo token é idempotente;
- ACK com token incompatível é rejeitado;
- o Gateway persiste antes do ACK;
- reentrega é deduplicada no Gateway por `event_id` único.

Nesta fase os eventos são apenas persistidos. A Fase 4 aplicará regras e
produzirá notificações, portanto nenhuma mensagem WhatsApp é enviada pela
outbox da Fase 3.
