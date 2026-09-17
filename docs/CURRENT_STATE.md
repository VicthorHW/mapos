Status: CURRENT

## S03-A target-validation state (TL_ACCEPTANCE)

- Feature `feature/customer-identity-auth-foundation-s03a` contains the dormant MapOS identity/credential authority; corrective commits under Technical Order 56 enforce the 10-attempt rate limit per token lifetime (stored in `tecnina_password_resets.attempts`, independent of wall-clock hourly rollover), strict subject validation (positive int `client_id`, canonical UUID `intake_id` returning HTTP 422 `invalid_payload`), true multi-connection verify concurrency regression testing (starting at attempts=4, row-locking serialization, released valid request rejected at 5th failure, attempts strictly capped at 5, `clientes.email` not promoted), upstream Traefik reverse-proxy audit (access logging disabled upstream in `coolify-proxy`, downstream Nginx redacts tokens to `[REDACTED]`), and verified Docker CLI host chronology.
- Git SHA terminology:
  - S03-A merge-base / canonical base: `e749e352a2952d8adc4e917c75ff0108d39d21f0`
  - Previously deployed S02 canonical runtime SHA: `76e72995cf4c00617435aeb34404aa28551533b2`
  - Validated & deployed S03-A runtime SHA: `027efe4ffb453bac363cfcc480c929d6aaf27ff6`
  - Feature branch HEAD: `027efe4ffb453bac363cfcc480c929d6aaf27ff6` (`HEAD == deployed`)
- Validated tables: `tecnina_client_identity`, `tecnina_client_profile`, `tecnina_email_verifications`, `tecnina_password_resets`, `tecnina_client_identity_phone_conflicts`, `tecnina_identity_rate_limits`.
- Rate limits: Email verification issue is capped at 3/15m and 10/day per subject+email; password reset issue capped at 3/hour/identity; credential hash capped at 60/min/service; client lookup capped at 300/min/service; public reset token capped at 10 attempts per token lifetime (tracked in `tecnina_password_resets.attempts`, attempt 11 returns 429 `rate_limited`); public reset IP capped at 30/hour.
- Password policy: minimum 6 Unicode characters (no composition requirement), 5 rejected, exact whitespace preserved, bcrypt 72-byte max.
- Password reset and email challenge TTL: exactly 15 minutes (900 seconds).
- Host Docker CLI diagnosis & repair chronology: `/usr/bin/docker` is the active clean binary (MD5 `8f880710f0f6e94aaaa960dd663bc001`, size 43,074,435 bytes), provided by Debian package `docker-ce-cli` (5:29.7.2-1~debian.12~bookworm arm64), `dpkg -V docker-ce-cli` verified clean with exit code 0. Root cause calibrated: "Docker CLI binary corruption confirmed; underlying physical corruption cause not determined."
- Production CodeIgniter migration completed once; ledger strictly preserved at `20260916120000` (zero reruns). Structural validation passed.
- Upstream reverse proxy & Nginx access log audit: Traefik (`coolify-proxy`, version 3.6) runs without `--accesslog` flag (access logging disabled upstream, zero request logs generated); downstream Nginx sanitizes `$request`, `$request_uri`, and `$http_referer` via `log_format tecnina_safe` in `default.conf` verified on target with 0 plaintext token occurrences in access logs.
- Test runner web protection: Nginx explicit block `location ^~ /tests/ { deny all; return 404; }`, CLI-only guard, and intentional safety interlock `TECNINA_TARGET_VALIDATION_AUTH='AUTHORIZED_S03A_TARGET_EXECUTION'`. Machine-readable results emitted outside web-served root to `/tmp/results_s03a.json`. Direct HTTP probes to `/tests/validate_s03a_target_final.php` and `/tests/results_s03a.json` return HTTP 404.
- Target validation suite: 129/129 assertions passed (100% EXECUTED_ON_TARGET, 0 failed). Target regression suite: 13 PASS, 2 accepted historical debts.
- Status: `TL_ACCEPTANCE` under Technical Order 56.
## S02-A — reconciliação atual (2026-09-16)

- S01 is `APPROVED / PUBLISHED`; S02-A is `CLOSED / ACCEPTED`. MapOS runs canonical `master` at `76e72995cf4c00617435aeb34404aa28551533b2` after Coolify deployment `gkpxgzavbwqqiftzfdul9qkj`; it remains functionally equivalent to accepted revision `a32ac998b58598f3bab45be0e790c3b46e111592`. The next active stage is S03 planning under a separate Technical Lead order.
- S02-A adds persistence-only pre-OS receiving, location, attachment metadata, and nullable approval snapshot/sync metadata. Its formal MapOS migration completed on the authorized target; no UI, auth, conversation or OS activation is included.
- Orange Pi 5 Pro / Coolify is the authoritative production MapOS runtime. The Client currently authorizes development/testing there because no important customer data is present. Windows is static source/documentation work only; no local MapOS runtime validation.
- PHP/runtime validation belongs on Orange Pi, not on Windows. Client controls Coolify branch, variables, lifecycle hooks and configuration; agent MCP is limited to permitted deploys and deployment logs.
- The first formal migration attempt exited before any ledger/schema change because `Tools` eagerly loaded dev-only Faker/Seeder dependencies while production Composer uses `--no-dev`; the migration framework was not the cause and no rollback was required. Commit `a32ac998b58598f3bab45be0e790c3b46e111592` lazily initializes those dependencies only in `seed()`.
- Coolify redeployment `oxzoyuklm86rbni20o2axday` used `a32ac998b58598f3bab45be0e790c3b46e111592`. Target `tools help` now exits 0 without Faker; the one authorized retry of `php index.php tools migrate 20260915120000` succeeded and advanced the ledger to `20260915120000`. Schema metadata and the S02 test passed; 12 of the 14 existing regression commands passed, with only the two documented baseline failures. Canonical deployment `gkpxgzavbwqqiftzfdul9qkj` then ran `master` at `76e72995cf4c00617435aeb34404aa28551533b2`: the ledger remains `20260915120000`, the three S02 tables and 11 approval metadata fields remain present, and PHP-FPM/Nginx/MySQL smoke checks passed. S02-A is closed and accepted.
Last consolidated: 2026-09-16
Source of truth: YES
Scope: MapOS fork TecNina / estado atual

---

# Estado atual — MapOS TecNina

## Base

- MapOS 4.54.0 no snapshot;
- CodeIgniter 3.1.13;
- PHP requerido `^8.4`;
- MySQL documentado em 8.4 no ambiente Docker.

## Integração TecNina vigente

- outbox MapOS para eventos do Gateway;
- endpoints privados `/api/bot/*` com bearer interno e whitelists;
- painel técnico `Configurações → WhatsApp` protegido por `cSistema`;
- pré-atendimentos em área operacional própria no menu, com revisão/aprovação via proxy server-side;
- bancada de testes do simulador (`Bot Lab`) em `tecnina_whatsapp/bot_lab` protegida por `cSistema`;
- criação idempotente de cliente/OS na aprovação;
- configuração logística/coleta administrada sem colocar estados logísticos dentro da OS;
- painel carrega áreas de forma incremental e consolida conexão/fila e mensagens automáticas;
- Flow Studio removido da UI/proxy;
- consulta vigente de reparo deve suportar cliente identificado pelo telefone e suas OS abertas via contrato mínimo;
- contratos privados aditivos para perfil mínimo, atualização cadastral, desvinculação segura de telefone, criação opcional de cliente e entrega de código por e-mail;
- aprovação de intake aceita credencial opcional e a grava pela biblioteca protegida já usada nas OS, sem copiá-la para observações;
- painel de pré-atendimentos permite ao operador oferecer taxa manual de coleta;
- interface de pré-atendimentos exibe a origem do GPS (WhatsApp ou navegador) e os rótulos de status para o gate da taxa manual ("Aguardando envio da taxa");
- payload de aprovação do pré-atendimento (`Intake_approval.php`) reconhece confirmação de taxa via WhatsApp (`ACCEPTED`) como liberação válida;
- biblioteca `Tecnina_phone.php` suporta números de telefone internacionais preservando proveniência de armazenamento.

## Bot Lab V2.1 (Bancada Administrativa do Simulador)

- **URL**: `tecnina_whatsapp/bot_lab`
- **Permissão de acesso**: protegida estritamente por `cSistema`.
- **Segurança do navegador**: autenticação por sessão do MapOS + token CSRF do CodeIgniter a cada requisição mutadora. O navegador NUNCA recebe o `MAPOS_BOT_TOKEN` e NUNCA faz chamadas diretas às rotas `/admin/simulator/*` do Bot.
- **Proxy Server-Side**: o controller `Tecnina_whatsapp.php` faz o papel de proxy seguro no backend, e a biblioteca `Tecnina_bot_gateway.php` efetua as chamadas HTTP server-to-server com bearer token.
- **Fonte da verdade**: a sessão de simulação no Bot é a única fonte da verdade para o estado da conversa, histórico e efeitos. O MapOS NÃO armazena estado conversacional nem histórico de simulação independente.
- **Papel da funcionalidade**: o Bot Lab é exclusivamente uma bancada administrativa de testes interativos (`admin test workbench`). NÃO é Flow Studio, NÃO é editor de FSM, NÃO é autoria de workflows, NÃO é um segundo motor de conversação e NÃO é visualizador de conversas de produção.
- **Capacidades da UI (V2.1)**:
  - Setup de fixtures e visualização de estado do runtime;
  - Resumo de configuração operacional no inspetor do Estado (cidades atendidas, taxas e agenda de entrega presencial);
  - Aba de inspeção de Entregas (inbox de código de registro simulado);
  - Geração segura de links clicáveis para formulários de capability via nós do DOM (`document.createElement`), sem injeção HTML;
  - Cards de eventos estruturados de CAPABILITY na transcrição da conversa;
  - Representação técnica refinada de passos CAPABILITY no inspetor de Etapas;
  - Console de envio de mensagens e localização, visualizador do ledger de passos e inspetor de efeitos externos observáveis.
- **Isolamento de Domínio**: O MapOS NÃO possui `SimulationRuntime`, NÃO gerencia tokens de capability, NÃO executa FSM, NÃO armazena snapshots de configuração operacional e NÃO faz roteamento HTTP de capabilities públicas. As páginas públicas de capability (`/s/{simulation_id}/...`) são servidas diretamente pelo Bot.
- **Status operacional (ADR-009)**: VALIDATED
- **Estado do código-fonte (Source State)**: PUBLISHED / SYNCED
- **Linha de base de código implantada (Deployed Implementation Baseline)**: `91857c92ec40c54c9d255c8109783164f3d0e848`
- **Implantação operacional (Deployment)**: DEPLOYED (Coolify Deployment #223)
- **Validação server-side**: PASS (rotas proxy do Bot Lab, autorização cSistema, contratos com o Bot Gateway validados no servidor)
- **Validação humana por navegador (Human Browser Acceptance)**: PASSED (Bancada do Bot Lab homologada formalmente pelo operador humano em navegador de produção).
- **Operação do V2.1**: Código V2.1 implantado em produção juntamente com Bot V2.1. Backups pré-deploy 20B mantidos no servidor (`/var/backups/tecnina/pre-20b/`).

## Bot Lab Automated Scenario Testing Workbench

- **URL**: `tecnina_whatsapp/bot_lab` (Aba Cenários Automatizados)
- **Papel da funcionalidade**: Bancada de testes e inspeção para cenários declarativos de conversação (`automated scenario test workbench`).
- **Divisão estrita de responsabilidades**:
  - **MapOS possui apenas**: apresentação do catálogo e resultados, UX de seleção e filtragem por tags/texto, e proxy administrativo server-side.
  - **Bot Gateway possui**: schema v1, catálogo de especificações YAML sob controle de versão, runner sequencial, execução isolada em SQLite efêmero, asserções, redaction de segredos e semântica de resultados.
- **O Bot Lab NÃO é**: editor visual de fluxos (Flow Studio), editor de cenários, nem segundo motor de conversação.
- **Status operacional (ADR-009)**: VALIDATED
- **Implantação operacional (Deployment)**: DEPLOYED (Coolify Deployment #242, baseline `6c119f20bef8d3edba060731f212f0b734e9f0a8`).
- **Validação server-side**: PASS (Run-all gateway: PASS; 23/23 cenários aprovados através do timeout específico de cenários do gateway implantado em produção; timeout genérico do gateway: 8s; timeout da suíte de cenários: 45s default).
- **Defeito de payload do Run All (Run All Payload Defect)**: RESOLVED / DEPLOYED (Causa raiz: objeto JSON vazio `{}` convertido para array PHP vazio `[]` via `json_decode(..., true)` no controller MapOS e serializado como `"[]"`, rejeitado com 422 pelo schema do Bot; correção implantada com decodificação `stdClass`, sanitização de timeouts e rejeição de arrays com 422).
- **Execução automatizada do Run All em navegador real de produção**: PASS (Playwright Chromium executou "Executar todos" no Bot Lab de produção: 23/23 cenários aprovados [0 FAIL / 0 ERROR], duração de parede 13,175 ms (~13.175 s), tempo do runner 12,635 ms (~12.635 s); 10 cards de cenários e 23 casos executáveis renderizados, expansão de card validada; 0 mutações de negócio no banco; 0 requisições diretas ao Bot admin; 0 Bearer expostos).
- **Validação humana por navegador (Human Browser Acceptance)**: PASSED (Ordem Técnica 21D: operador humano homologou em produção a abertura, catálogo, filtros, seleção e resultados, e concluiu o reteste pessoal do "Executar todos" com 23 PASS / 0 FAIL / 0 ERROR; REQ-AST-025 DONE).

## Baselines de Produção e Desenvolvimento

### Produção Vigente (Current Production — Automated Scenario Testing Phase 1 & Bot Lab V2.1)
- **Status de ciclo de vida (ADR-009)**: VALIDATED
- **Linha de base implantada (Deployed Implementation Baseline)**: `6c119f20bef8d3edba060731f212f0b734e9f0a8` (Coolify Deployment #242)
- **Validação server-side**: PASS (Run-all gateway: PASS; 23/23 cenários aprovados através do timeout específico de cenários do gateway implantado; timeout genérico: 8s, timeout de cenários: 45s default)
- **Aceite humano via browser (Bot Lab)**: PASSED (Ordem Técnica 21D: homologação humana formal concluída pelo operador em produção; "Executar todos" 23 PASS / 0 FAIL / 0 ERROR; REQ-AST-025 DONE)
- **Estado do código-fonte (Source State)**: PUBLISHED / SYNCED
- **Branch de publicação vigente**: `master`
- **Backups pré-deploy (21C)**: Mantidos no servidor em `/home/orangepi/backups/tecnina/manual/20260913T195644Z-automated-scenarios/`
- **Conjuntos de backups anteriores (18B, 20B)**: Preservados intactos
- **Validação automatizada local**:
  - 6 testes de contrato PHP executados com 277 asserções no total:
    - `tests/TecninaBotGatewayTimeoutTest.php`: 82 asserções (inclui atualização da asserção I e casos comportamentais A-K para deserialização/serialização de payload de cenários e sanitização de timeouts)
    - `tests/TecninaBotLabPanelTest.php`: 135 asserções (inclui contratos AC-AG de hierarquia DOM, balanceamento de tags, timeout cliente e estados de catálogo)
    - `tests/TecninaIntakeReviewPanelTest.php`: 29 asserções
    - `tests/TecninaLogisticsPanelTest.php`: 17 asserções
    - `tests/TecninaOsAccessPanelTest.php`: 8 asserções
    - `tests/TecninaFlowStudioRemovalTest.php`: 6 asserções
  - 1 suíte de teste de interface DOM / JavaScript:
    - `tests/TecninaBotLabUiTest.js`: 38 asserções passadas cleanly (hierarquia DOM, ativação, renderização do catálogo, idempotência de cliques repetidos, tela de erro com alert visível, despachos únicos de execução e contratos de payload do navegador para Run All e Run Selected).
  - Sintaxe JavaScript e PHP estritamente verificadas (`node --check assets/tecnina/js/bot-lab.js`, `php -l`).

## Confirmações no repositório real

- `GET /api/bot/client/{client_id}/open-os` está implementado por `application/controllers/api/bot/Client_open_os.php` e `application/models/Tecnina_client_open_os_model.php`;
- a rota está registrada em `application/config/routes.php`;
- controller e model constam no instalador `tools/tecnina-integration/install.php`;
- `tests/TecninaIntakeApprovalTest.php` verifica rota e instalação;
- o mecanismo antigo de código de oito caracteres não está exposto pelo painel; endpoints residuais retornam desativação explícita;
- a UI e o proxy do Flow Studio estão removidos; testes de regressão cobrem essa ausência;
- o painel não oferece emissão manual do link legado de localização; a coleta é iniciada automaticamente pelo fluxo vigente do Gateway em `/g/{token}`;
- `os.observacoes` é visível no portal/PDF/e-mail, enquanto `anotacoes_os` não é consultada por esses canais; metadados do intake passam a usar Anotações;
- endereço de coleta não altera o endereço cadastral de cliente existente nem é copiado silenciosamente ao criar cliente novo;
- revisão de pré-atendimento apresenta GPS em mapa OpenStreetMap e oferece link de coordenadas para o Google Maps, sem API paga;
- o Bot Lab possui entrada no menu lateral administrativo (`application/views/tema/menu.php`) condicionada a `cSistema`.

## Legado que não deve orientar novas mudanças

- Flow Studio: removido; tabelas históricas no banco do Gateway podem ficar inertes.
- consulta por código de 8 caracteres: desenho anterior, não é o fluxo conversacional atual.
- documentos de link `/l`/mapa: substituídos pelo contrato atual do Gateway.

## Próxima validação necessária

- estabilização pós-deploy e monitoramento de logs de produção;
- acompanhamento da governança de testes de cenários conversacionais (Fase 22A+).

## S03-A — identity and credential authority (TARGET_VALIDATION)

- CIAO-S03A is in `TARGET_VALIDATION` on `feature/customer-identity-auth-foundation-s03a`; it remains dormant and does not cut over the active `Mine.php` login/profile flow.
- The preflight makes unresolved relational phone-conflict evidence authoritative over a nominal unique identity row, materializes the client e-mail `PENDING` state only after successful challenge delivery, preserves the trusted `clientes.email` until verification, and counts only well-formed wrong verification codes.
- Password policy enforces minimum 6 Unicode characters (no composition requirement), exactly preserving whitespace.
- Verification and reset challenge TTL is exactly 15 minutes (900 seconds).
- Rate limits: Email verification issue is capped at 3/15m and 10/day per subject+email; password reset issue capped at 3/hour/identity; credential hash capped at 60/min/service; client lookup capped at 300/min/service; public reset token capped at 10/hour; public reset IP capped at 30/hour.
- Schema structures added and verified: `tecnina_client_identity`, `tecnina_client_profile`, `tecnina_email_verifications`, `tecnina_password_resets`, `tecnina_client_identity_phone_conflicts`, `tecnina_identity_rate_limits`.
- Docker CLI bit-corruption was repaired with clean package extraction; calibrated root cause: "Docker CLI binary corruption confirmed; underlying corruption cause not determined."
- Nginx access-log token redaction verified (0 occurrences).
- Technical Order 55 hardening: row-locking concurrency guard on email verification attempts, same-candidate reissue support without affected_rows error, syntactic validation for reset requests returning 422, Nginx `/tests/` protection returning 404, CLI safety interlock, and machine-readable output outside web-served root.
