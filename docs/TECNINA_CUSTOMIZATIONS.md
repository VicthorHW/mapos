Status: CURRENT
Last consolidated: 2026-09-17
Source of truth: YES
Scope: MapOS fork TecNina / manifesto de customizações

---

# Manifesto de customizações TecNina

Objetivo: permitir atualização do upstream sem perder de vista o que a fork adiciona ou altera.

## Registro consolidado

| ID | Capability | Estado | Principais áreas | Contrato/teste |
|---|---|---|---|---|
| MAP-01 | Outbox de eventos | CURRENT | setup, model, trigger, install/post-deploy | testes de outbox/claim/ACK |
| MAP-02 | Contexto mínimo para notificações | CURRENT | routes + controller/model TecNina | contract test |
| MAP-03 | Identificação de cliente por telefone | CURRENT | `/api/bot/client/by-phone` | teste de ambiguidade/whitelist |
| MAP-04 | Painel Configurações → WhatsApp | CURRENT | controller, gateway client, view, JS | autorização `cSistema` |
| MAP-05 | Revisão e aprovação de intake | CURRENT | painel + endpoint/model de aprovação | idempotência/concorrência |
| MAP-06 | Configuração logística/coleta | CURRENT | `application/controllers/Tecnina_whatsapp.php`, `application/views/tecnina_whatsapp/index.php`, `assets/tecnina/js/whatsapp-panel.js` | sem colunas logísticas na OS; sem emissor manual `/l` |
| MAP-07 | Carregamento incremental do painel | CURRENT | `assets/tecnina/js/whatsapp-panel.js` | leitura retry breve; escrita sem retry automático |
| MAP-08 | OS abertas por cliente para consulta atual | CURRENT | `application/controllers/api/bot/Client_open_os.php`, `application/models/Tecnina_client_open_os_model.php`, `application/config/routes.php` | `tests/TecninaIntakeApprovalTest.php` |
| MAP-09 | Flow Studio | SUPERSEDED/REMOVED | antiga aba/proxy | não reativar; histórico em archive |
| MAP-10 | Código de 8 caracteres / consulta por OS | SUPERSEDED | rota/status + UI antiga | fluxo atual usa telefone + OS abertas |
| MAP-11 | Gestão WhatsApp e pré-atendimentos separados | DEPLOYED / HISTORICALLY_VALIDATED | controller/view/JS/CSS TecNina + item no menu original | testes de painel, privacidade e aprovação |
| MAP-12 | Prévia administrativa do GPS | DEPLOYED / HISTORICALLY_VALIDATED | JS/CSS da revisão de intake + detalhe autenticado do Gateway | mapa sem API paga; coordenadas fora da listagem |
| MAP-13 | Perfil e cadastro privados do Bot | DEPLOYED / HISTORICALLY_VALIDATED | controllers/model TecNina + rotas `/api/bot/*` | whitelist, token interno, sem hash/secret |
| MAP-14 | Credencial no intake aprovado | DEPLOYED / HISTORICALLY_VALIDATED | extensão do controller/model TecNina de aprovação | `Device_credential`, sem conteúdo em anotações |
| MAP-15 | Oferta de taxa manual | DEPLOYED / HISTORICALLY_VALIDATED | proxy e painel de pré-atendimento | operador autenticado, versão otimista e aceite no Gateway |
| MAP-16 | Bot Lab V2.1 (bancada do simulador com config operacional, entregas e capabilities) | DEPLOYED / VALIDATED | controller `Tecnina_whatsapp.php`, library `Tecnina_bot_gateway.php`, view `bot_lab.php`, assets `bot-lab.js` e `bot-lab.css` | `tests/TecninaBotLabPanelTest.php` |
| MAP-19 | S03-A identity and credential authority foundation | TL_ACCEPTANCE | additive migration, `Tecnina_identity_authority`, private identity controller, public reset boundary, DB-backed limiter and Nginx safe access-log mapping | production ledger `20260916120000`; TO 57 closures: reset replay/rate-limit precedence, password confirmation enforcement, fail-closed attempt accounting (503), client_id positive int (422), upstream Traefik proxy audit, 146/146 target assertions PASS |

## S02-A data foundation

MAP-18: persistence-only pre-OS receiving, location, attachment metadata, and approval snapshot/sync metadata. Status `S02-A_CLOSED_ACCEPTED`; canonical MapOS `master` is deployed as `gkpxgzavbwqqiftzfdul9qkj` at `76e72995cf4c00617435aeb34404aa28551533b2`, functionally equivalent to accepted revision `a32ac998b58598f3bab45be0e790c3b46e111592`; ledger remains `20260915120000`. Bot canonical `main` is deployed as `eq2bysugtgj0fhkjeus3rwdt` at `44f470cea3136bc64974344c4618e98b5ae4e845`; Alembic remains `20260915_0021`. The production CLI correction in `application/controllers/Tools.php` lazy-loads dev-only Faker/Seeder only for `seed()` so migration commands remain compatible with Composer `--no-dev`. Runtime/PHP validation belongs to Orange Pi 5 Pro / Coolify, not to a local Windows MapOS runtime.

## Arquivo upstream alterado neste ciclo

| Arquivo | Motivo | Necessidade | Alternativa avaliada | Proteção |
|---|---|---|---|---|
| `application/views/tema/menu.php` | entrada operacional Pré-atendimentos | a barra lateral central é a navegação padrão do MapOS | manter dentro de Configurações contrariaria o fluxo diário | `TecninaIntakeReviewPanelTest.php` |
| `application/views/tema/menu.php` | entrada administrativa Bot Lab | navegação administrativa integrada no menu lateral sob `cSistema` | submenu secundário dificultaria acesso do operador técnico | `tests/TecninaBotLabPanelTest.php` |
| `application/config/routes.php` | publicar contratos privados aditivos do Gateway | CodeIgniter centraliza o roteamento nesta configuração | rotas implícitas não preservariam os paths estáveis `/api/bot/*` | `TecninaBotClientProfileTest.php` e `TecninaIntakeApprovalTest.php` |
| `application/views/os/emails/clientenovo.php` | orientar primeiro acesso do cliente criado pelo WhatsApp | o e-mail existente é a comunicação inicial já adotada pelo MapOS | criar e-mail paralelo duplicaria o gatilho e o template | testes estáticos da integração + E2E pendente |

## Regra para arquivos upstream

Sempre que uma customização alterar arquivo original do MapOS, registrar:

`arquivo | motivo | por que é necessário | alternativa considerada | teste que protege a alteração`.

## Regras de manutenção

- minimizar alterações em arquivos upstream;
- preferir controllers/models/libraries TecNina novos;
- preservar contratos privados estáveis;
- rodar instalador em modo de verificação após atualização upstream;
- não colocar WhatsApp/Evolution dentro do MapOS;
- não reintroduzir funcionalidade marcada `SUPERSEDED` porque ainda exista tabela/arquivo histórico.
