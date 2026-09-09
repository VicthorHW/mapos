Status: CURRENT
Last consolidated: 2026-09-09
Source of truth: YES
Scope: MapOS↔Bot Gateway / contratos privados

---

# Contratos de integração TecNina no MapOS

## Princípios

- endpoints privados, autenticados e mínimos;
- nenhuma API administrativa genérica é fonte do Bot;
- cada resposta usa whitelist explícita;
- Gateway nunca consulta tabelas MapOS diretamente;
- falha da Evolution não pode impedir atualização de OS no MapOS.

## Contratos consolidados

| Função | Contrato lógico | Dados permitidos |
|---|---|---|
| health | `/api/bot/health` ou rota vigente equivalente | status mínimo |
| outbox | claim/ACK em `/api/bot/outbox/*` | eventos TecNina mínimos |
| contexto de integração | `/api/bot/integration-context/{os_id}` | whitelist necessária à notificação |
| identificação por telefone | `/api/bot/client/by-phone` | `none`/`unique(client_id)`/`ambiguous` |
| OS abertas do cliente | `GET /api/bot/client/{client_id}/open-os` | `os_id`, status, identificação curta de equipamento |
| perfil mínimo | `GET/PATCH /api/bot/client/{client_id}/profile` | id, nome, telefone normalizado e endereço permitido |
| desvincular telefone | `POST /api/bot/client/{client_id}/unlink-phone` | confirmação booleana, com comparação normalizada |
| criar cliente opcional | `POST /api/bot/clients` | resultado `created`/`existing` e `client_id` |
| enviar código de e-mail | `POST /api/bot/client-registration/email-code` | somente confirmação de enfileiramento |
| aprovação intake | endpoint privado TecNina de aprovação | payload aprovado e credencial própria; criação idempotente |

`GET /api/bot/os/{os_id}/status` e mecanismo de código de consulta pertencem a uma geração anterior. Não devem ser usados para restaurar o fluxo antigo sem uma nova decisão explícita.

## Telefone

Normalizar identidade brasileira de forma determinística e rejeitar ambiguidade. Não usar `LIKE` parcial que possa alcançar outro titular.

## Aprovação

A aprovação deve revalidar correspondência do cliente no MapOS imediatamente
antes da gravação e impedir duplicidade de OS por reenvio. Quando a credencial
opcional foi informada por capability segura, o MapOS valida e cifra novamente
por `Device_credential`; conteúdo real nunca entra em observações ou respostas
da API.

## Cadastro e perfil

Os contratos rejeitam campos extras e não retornam senha, hash, documento ou
secrets. Cadastro opcional recebe o telefone normalizado da conversa; o código
de confirmação é enfileirado pelo mecanismo de e-mail do MapOS. A desvinculação
de número reciclado limpa apenas os campos que realmente correspondem ao
telefone confirmado e nunca apaga cliente, OS ou histórico.

Uma repetição idempotente de criação só retorna `existing` quando telefone, CPF
e e-mail correspondem ao mesmo registro. Telefone existente com identidade
divergente retorna conflito e exige revisão humana.

## Evolução de contratos

Toda alteração deve ser aditiva sempre que possível, possuir teste de contrato e ser registrada em `TECNINA_CUSTOMIZATIONS.md`.
