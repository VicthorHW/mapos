Status: CURRENT

## S03-A canonical deployment state (DONE / CANONICAL_CLOSURE_COMPLETE)

- **Feature Branch**: `feature/customer-identity-auth-foundation-s03a` (preserved for audit history; merged to `master`)
- **Canonical Branch**: `master` (Coolify branch `master`, commit `HEAD`)
- **Current Stage**: `DONE` (Formally accepted by Technical Lead under Technical Order 58; canonical deployment complete)
- **Git SHA Terminology**:
  - **S03-A merge-base / canonical base**: `e749e352a2952d8adc4e917c75ff0108d39d21f0`
  - **Previously deployed S02 canonical runtime SHA**: `76e72995cf4c00617435aeb34404aa28551533b2`
  - **Accepted S03-A Feature HEAD**: `baf7cd6a5ec19947dd61a2963d233db13e628257`
  - **Accepted Functional Runtime SHA**: `f07620f3bcc49951cdad356acadad4ed8d5cd265`
  - **Canonical Master Merge SHA**: `96bada83908f4caae364ff2b8b97c400b0267265`
  - **Canonical Deployed Runtime SHA**: `96bada83908f4caae364ff2b8b97c400b0267265` (Coolify Deployment #267)
- **Active Containers (Coolify Deployment #267, UUID `qdndixmaltnp1qvznmfkqwht`)**:
  - PHP-FPM: `php-fpm-l29tpqli0yt1usg25981aouz-225726174718` (id `fa131132b6df`)
  - Nginx: `nginx-l29tpqli0yt1usg25981aouz-225726061688` (id `0e27219e1a92`)
  - MySQL: `mysql-l29tpqli0yt1usg25981aouz-225726316835` (id `ed45d76796b9`, healthy)
- **Production Migration Ledger**:
  - Migration completed once; ledger strictly preserved at `20260916120000` (zero reruns).
  - Schema tables verified pristine: `tecnina_client_identity`, `tecnina_client_profile`, `tecnina_email_verifications`, `tecnina_password_resets`, `tecnina_client_identity_phone_conflicts`, `tecnina_identity_rate_limits`.
- **Target Validation Suite**:
  - Executed on target container: `validate_s03a_target_final.php --authorized-s03a-target-execution`
  - Result: 146 passed, 0 failed (100% EXECUTED_ON_TARGET).
  - Machine-readable results emitted outside web root to `/tmp/results_s03a.json`.
- **Target Regression Suite**:
  - Standard regression commands: 12 PASS, 2 accepted historical baseline debts (`TecninaIntegrationContextTest`, `TecninaIntakeApprovalTest`).
  - Additional S03 test: `tests/TecninaS03IdentityAuthorityTest.php` PASS with 28/28 assertions.
  - Combined suite total: 13 PASS, 2 accepted historical debts (15 tests total).
- **Core Security & Contract Closures (Technical Order 57)**:
  - **Public Reset Replay / Rate-Limit Precedence**: In `consumePasswordReset()`, token existence and validity checks precede rate limit checks:
    1. Token does not exist -> generic `invalid_or_expired_reset` (HTTP 409).
    2. Token is not `PENDING` or is expired -> generic `invalid_or_expired_reset` (HTTP 409).
    3. Still-valid `PENDING` token has reached 10-attempt lifetime allowance -> `rate_limited` (HTTP 429).
    4. Process valid submission: attempt 10 with valid password succeeds (HTTP 200, state `CONSUMED`, attempts 10, credential version increments once). Replay returns generic HTTP 409 `invalid_or_expired_reset` (NOT 429) without mutating password or version. Expired `PENDING` token with attempts=10 returns generic HTTP 409 (NOT 429).
  - **Public Reset Password Confirmation**: Confirmation is strictly required on public reset consumption (`password_confirmation`). Missing, mismatched, or non-string confirmation is rejected as an invalid attempt (HTTP 409 `invalid_or_expired_reset`) and increments attempt counter. Exact byte-for-byte confirmation accepted.
  - **Fail-Closed Attempt Accounting**: Both `verifyEmail()` wrong-code attempt increment and `consumePasswordReset()` invalid-password attempt increment verify that affected rows equal 1 and transaction status is true under row lock (`SELECT ... FOR UPDATE`); database write persistence failure returns HTTP 503 `unavailable`.
  - **Client ID Validation in `issuePasswordReset()`**: Strictly requires positive integer representation (`is_int($v) ? $v > 0 : (is_string($v) && ctype_digit($v) && (int)$v > 0)`). Malformed/non-positive values (`0`, `-1`, `"0"`, `"-1"`, `"abc"`) return HTTP 422 `invalid_payload`.
  - **Rate Limits Summary**:
    - Email verification issue: 3/15m and 10/day per subject+email.
    - Password reset issue: 3/hour/identity.
    - Credential hash: 60/min/service.
    - Client lookup: 300/min/service.
    - Public reset token lifetime: strictly 10 attempts per token lifetime (stored in `tecnina_password_resets.attempts`, attempt 11 returns HTTP 429 `rate_limited`).
    - Public reset IP: 30/hour/IP.
  - **Password Policy & TTL**: Minimum 6 Unicode characters (no composition requirement), exact whitespace preserved, bcrypt 72-byte max. Verification and reset challenge TTL is exactly 15 minutes (900 seconds).
  - **Reverse Proxy & Access Log Redaction**: Upstream Traefik (`coolify-proxy`, v3.6) runs without `--accesslog` flag (access logging disabled upstream, zero request logs generated); downstream Nginx sanitizes `$request`, `$request_uri`, and `$http_referer` via `log_format tecnina_safe` in `default.conf` verified on target with 0 plaintext token occurrences in access logs (`[REDACTED]`).
  - **Test Runner Web Protection**: Nginx explicit block `location ^~ /tests/ { deny all; return 404; }`, CLI-only guard, and safety interlock `TECNINA_TARGET_VALIDATION_AUTH='AUTHORIZED_S03A_TARGET_EXECUTION'`. Machine-readable results emitted outside web root to `/tmp/results_s03a.json`. Direct HTTP probes to `/tests/validate_s03a_target_final.php` and `/tests/results_s03a.json` return HTTP 404.
  - **Host Docker CLI Chronology**:
    1. Initial observed failure: Go runtime / invalid symbol-table / invalid pc-encoded-table failure during Docker CLI execution while copying deployment configuration in Coolify deployment `zge8l7gv8yvydqcnn6gatpqt` (`fatal error: runtime: invalid symbol table / invalid pc-encoded table in runtime.pcvalue`);
    2. Diagnostic symptoms: interactive shell execution of `/usr/bin/docker` produced `SIGSEGV` and `SIGILL` on host due to binary corruption around offset ~19251201;
    3. Package integrity check: `dpkg -V docker-ce-cli` confirmed file hash mismatch on `/usr/bin/docker`;
    4. Clean package replacement: clean `/usr/bin/docker` extracted from official Debian package `docker-ce-cli` (5:29.7.2-1~debian.12~bookworm arm64), verified clean via `dpkg -V` (exit code 0);
    5. Current healthy state: active clean binary at `/usr/bin/docker` (MD5 `8f880710f0f6e94aaaa960dd663bc001`, size 43,074,435 bytes) operating reliably across all deployments.
    Calibrated root cause: "Docker CLI binary corruption confirmed; underlying physical corruption cause not determined."

---

## S02-A — Histórico de Fechamento (2026-09-16) [HISTORICAL / CLOSED]

- S01 is `APPROVED / PUBLISHED`; S02-A is `CLOSED / ACCEPTED`. MapOS canonical `master` deployment `gkpxgzavbwqqiftzfdul9qkj` deployed `76e72995cf4c00617435aeb34404aa28551533b2`, functionally equivalent to accepted revision `a32ac998b58598f3bab45be0e790c3b46e111592`. S02-A closed all data foundation requirements. Active development transitioned to S03-A (`feature/customer-identity-auth-foundation-s03a`), currently in `TL_ACCEPTANCE`.
- S02-A added persistence-only pre-OS receiving, location, attachment metadata, and nullable approval snapshot/sync metadata. Its formal MapOS migration completed on the authorized target at ledger version `20260915120000`; no UI, auth, conversation or OS activation was included.
- The first formal migration attempt exited before any ledger/schema change because `Tools` eagerly loaded dev-only Faker/Seeder dependencies while production Composer uses `--no-dev`; the migration framework was not the cause and no rollback was required. Commit `a32ac998b58598f3bab45be0e790c3b46e111592` lazily initializes those dependencies only in `seed()`.
- Redeployment `oxzoyuklm86rbni20o2axday` used `a32ac998b58598f3bab45be0e790c3b46e111592`. Target `tools help` exited 0 without Faker; the authorized retry of `php index.php tools migrate 20260915120000` succeeded and advanced the ledger to `20260915120000`. Schema metadata and the S02 test passed; 12 of the 14 existing regression commands passed, with only the two documented baseline failures. Canonical deployment `gkpxgzavbwqqiftzfdul9qkj` ran `master` at `76e72995cf4c00617435aeb34404aa28551533b2`: ledger remained `20260915120000`, the three S02 tables and 11 approval metadata fields remained present, and smoke checks passed. S02-A was closed and accepted.
Last consolidated: 2026-09-17
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
