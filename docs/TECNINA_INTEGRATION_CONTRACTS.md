Status: CURRENT
Last consolidated: 2026-09-12
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
| proxy simulador (Bot Lab) | proxy server-to-server `/admin/simulator/*` | fixtures, mensagens, coordenadas, reset, delete |

`GET /api/bot/os/{os_id}/status` e mecanismo de código de consulta pertencem a uma geração anterior. Não devem ser usados para restaurar o fluxo antigo sem uma nova decisão explícita.

## Contrato do Gateway com o Simulador do Bot (Bot Lab Proxy)

O `Tecnina_bot_gateway` implementa o proxy server-to-server entre o MapOS e a Admin API do Bot (`/admin/simulator/*`):

### Mapeamento Seguro de Reason Codes do Bot
Em caso de respostas de erro da Admin API do Bot, o Gateway extrai e preserva com segurança os códigos canônicos:
- `simulation_not_found`: Sessão solicitada não existe ou foi descartada.
- `simulation_fixture_incomplete`: Fixture obrigatória não fornecida para o cenário.
- `simulation_runtime_state`: Operação inválida no estado atual do runtime (ex: envio em sessão `FAULTED` ou `CLOSED`).
- `simulation_manager_unavailable`: Falha ou indisponibilidade interna no gerenciador de sessões.
- `simulation_execution_failed`: Falha inesperada durante o processamento do passo pelo motor.

### Tratamento Genérico de Respostas HTTP 204 (No Content)
- Quando o Bot retorna HTTP 204 com corpo vazio (`empty body`), o `Tecnina_bot_gateway` interpreta a resposta como sucesso operacional (`success: true`) com carga útil nula (`data: null`).

### Ciclo de Exclusão de Sessão pelo Navegador
1. O navegador envia uma requisição `POST` autenticada por sessão ao MapOS (`tecnina_whatsapp/bot_lab_delete_session`) contendo o token CSRF obrigatório.
2. O controller do MapOS invoca o método de gateway que emite um `DELETE` HTTP server-to-server autenticado com bearer token para o Bot (`/admin/simulator/sessions/{simulation_id}`).
3. O Bot conclui a exclusão da sessão em memória e responde `HTTP 204 No Content`.
4. O controller do MapOS intercepta o sucesso do gateway e responde com `HTTP 200 JSON` ao navegador contendo `{ success: true, ... }` e o token CSRF devidamente regenerado.

## Telefone e Proveniência de Armazenamento

A correspondência de identidade telefônica separa formalmente a identidade canônica de transporte da proveniência de armazenamento no MapOS:

- **Identidade Canônica de Transporte**: Utilizada nas trocas internas entre Evolution, Bot e API do MapOS (`GET /api/bot/client/by-phone`). É representada **estritamente em dígitos** (8 a 15 dígitos). O DDI internacional é preservado integralmente; nunca se infere Brasil com base em extensão de dígitos ou prefixos locais coincidentes. Se o DDI for explicitamente 55, a estrutura brasileira é preservada e o 9º dígito móvel legado é normalizado. Identidades canônicas não são rejeitadas apenas por terem formato semelhante a telefone fixo.
- **Armazenamento de Identidade Canônica Internacional no MapOS**: Registros internacionais gravados por integrações da TecNina utilizam **obrigatoriamente o prefixo `+`** (ex: `+351911872552`, `+66912345678`, `+14155552671`). O marcador `+` define proveniência inequívoca e impede colisão com números legados locais.
- **Armazenamento Canônico Brasileiro no MapOS**: Registros canônicos nacionais permanecem como dígitos iniciando explicitamente por `55` (ex: `5541997403509`).
- **Armazenamento Local / Legado Brasileiro no MapOS**: Registros existentes no banco de dados sem `+` e sem `55` (ex: `4197403509`, `66912345678`) são interpretados **estritamente como dados locais/legados brasileiros**, gerando aliases canônicos 55. Um valor não marcado de 10/11 dígitos **não** é interpretado simultaneamente como internacional e brasileiro. Registros internacionais legados sem `+` exigem normalização para `+<canônico>`.
- **Casamento Determinístico**: A consulta compara a identidade canônica de transporte contra o conjunto de identidades derivadas da proveniência do candidato. O uso de `LIKE` parcial é estritamente proibido. Resposta de identidade ambígua (>1 cliente) é reportada com segurança (`match: ambiguous`) e escala para atendimento humano.
- **Leitura e Retorno de Perfil (`GET/PATCH /api/bot/client/{client_id}/profile`)**: Os valores armazenados no banco (`celular`, `telefone`) são convertidos de volta para a identidade canônica de transporte em dígitos puros (`canonicalIdentityFromStored()`). O marcador `+` de números internacionais nunca é exposto na resposta da API, e números legados locais retornam sua identidade canônica correspondente.

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
