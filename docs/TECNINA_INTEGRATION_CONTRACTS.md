Status: CURRENT
Last consolidated: 2026-09-09
Source of truth: YES
Scope: MapOSâ†”Bot Gateway / contratos privados

---

# Contratos de integraÃ§Ã£o TecNina no MapOS

## PrincÃ­pios

- endpoints privados, autenticados e mÃ­nimos;
- nenhuma API administrativa genÃ©rica Ã© fonte do Bot;
- cada resposta usa whitelist explÃ­cita;
- Gateway nunca consulta tabelas MapOS diretamente;
- falha da Evolution nÃ£o pode impedir atualizaÃ§Ã£o de OS no MapOS.

## Contratos consolidados

| FunÃ§Ã£o | Contrato lÃ³gico | Dados permitidos |
|---|---|---|
| health | `/api/bot/health` ou rota vigente equivalente | status mÃ­nimo |
| outbox | claim/ACK em `/api/bot/outbox/*` | eventos TecNina mÃ­nimos |
| contexto de integraÃ§Ã£o | `/api/bot/integration-context/{os_id}` | whitelist necessÃ¡ria Ã  notificaÃ§Ã£o |
| identificaÃ§Ã£o por telefone | `/api/bot/client/by-phone` | `none`/`unique(client_id)`/`ambiguous` |
| OS abertas do cliente | `GET /api/bot/client/{client_id}/open-os` | `os_id`, status, identificaÃ§Ã£o curta de equipamento |
| perfil mÃ­nimo | `GET/PATCH /api/bot/client/{client_id}/profile` | id, nome, telefone normalizado e endereÃ§o permitido |
| desvincular telefone | `POST /api/bot/client/{client_id}/unlink-phone` | confirmaÃ§Ã£o booleana, com comparaÃ§Ã£o normalizada |
| criar cliente opcional | `POST /api/bot/clients` | resultado `created`/`existing` e `client_id` |
| enviar cÃ³digo de e-mail | `POST /api/bot/client-registration/email-code` | somente confirmaÃ§Ã£o de enfileiramento |
| aprovaÃ§Ã£o intake | endpoint privado TecNina de aprovaÃ§Ã£o | payload aprovado e credencial prÃ³pria; criaÃ§Ã£o idempotente |

`GET /api/bot/os/{os_id}/status` e mecanismo de cÃ³digo de consulta pertencem a uma geraÃ§Ã£o anterior. NÃ£o devem ser usados para restaurar o fluxo antigo sem uma nova decisÃ£o explÃ­cita.

## Telefone

Normalizar identidade brasileira de forma determinÃ­stica e rejeitar ambiguidade. NÃ£o usar `LIKE` parcial que possa alcanÃ§ar outro titular.

## AprovaÃ§Ã£o

A aprovaÃ§Ã£o deve revalidar correspondÃªncia do cliente no MapOS imediatamente
antes da gravaÃ§Ã£o e impedir duplicidade de OS por reenvio. Quando a credencial
opcional foi informada por capability segura, o MapOS valida e cifra novamente
por `Device_credential`; conteÃºdo real nunca entra em observaÃ§Ãµes ou respostas
da API.

## Cadastro e perfil

Os contratos rejeitam campos extras e nÃ£o retornam senha, hash, documento ou
secrets. Cadastro opcional recebe o telefone normalizado da conversa; o cÃ³digo
de confirmaÃ§Ã£o Ã© enfileirado pelo mecanismo de e-mail do MapOS. A desvinculaÃ§Ã£o
de nÃºmero reciclado limpa apenas os campos que realmente correspondem ao
telefone confirmado e nunca apaga cliente, OS ou histÃ³rico.

Uma repetiÃ§Ã£o idempotente de criaÃ§Ã£o sÃ³ retorna `existing` quando telefone, CPF
e e-mail correspondem ao mesmo registro. Telefone existente com identidade
divergente retorna conflito e exige revisÃ£o humana.

## EvoluÃ§Ã£o de contratos

Toda alteraÃ§Ã£o deve ser aditiva sempre que possÃ­vel, possuir teste de contrato e ser registrada em `TECNINA_CUSTOMIZATIONS.md`.

