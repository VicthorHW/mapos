Status: CURRENT
Last consolidated: 2026-09-08
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
| aprovação intake | endpoint privado TecNina de aprovação | somente payload aprovado; criação idempotente |

`GET /api/bot/os/{os_id}/status` e mecanismo de código de consulta pertencem a uma geração anterior. Não devem ser usados para restaurar o fluxo antigo sem uma nova decisão explícita.

## Telefone

Normalizar identidade brasileira de forma determinística e rejeitar ambiguidade. Não usar `LIKE` parcial que possa alcançar outro titular.

## Aprovação

A aprovação deve revalidar correspondência do cliente no MapOS imediatamente antes da gravação e impedir duplicidade de OS por reenvio. Credencial física do aparelho não é coletada automaticamente pelo Bot.

## Evolução de contratos

Toda alteração deve ser aditiva sempre que possível, possuir teste de contrato e ser registrada em `TECNINA_CUSTOMIZATIONS.md`.
