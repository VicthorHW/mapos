Status: CURRENT
## S03-A — estado atual (DONE / CANONICAL_CLOSURE_COMPLETE)
- S03-A formalmente aceito pelo Technical Lead sob a Technical Order 58 e fechado com deploy canônico no master.
- Merge canônico no `master` (`96bada83908f4caae364ff2b8b97c400b0267265`) e Coolify Deployment #267 (`qdndixmaltnp1qvznmfkqwht`) com status `finished` (exit code 0).
- Containers canônicos ativos: PHP-FPM (`php-fpm-l29tpqli0yt1usg25981aouz-225726174718`), Nginx (`nginx-l29tpqli0yt1usg25981aouz-225726061688`), MySQL (`mysql-l29tpqli0yt1usg25981aouz-225726316835`).
- Ledger de migração estritamente mantido em `20260916120000` (zero reexecuções).
- Suíte de regressão canônica: 13 PASS, 2 débitos históricos aceitos (total de 15 testes). TecninaS03IdentityAuthorityTest: 28/28 asserções PASS. Zero novas regressões.
- Proteção `/tests/` no Nginx retornando HTTP 404 e sanitização de tokens ativa via `log_format tecnina_safe`.
- Fechamento de contrato de rate limit de reset: precedência estrita (token inexistente/expirado/consumido retorna 409 antes do rate limit 429), 10 tentativas por tempo de vida do token (armazenado em `tecnina_password_resets.attempts`), tentativa 11 retorna HTTP 429 `rate_limited`. Replay de token consumido retorna HTTP 409 `invalid_or_expired_reset` (não 429). Token PENDING expirado com 10 tentativas retorna HTTP 409 (não 429).
- Confirmação de senha obrigatória em reset público (`password_confirmation`), rejeitando ausência ou divergência com 409 e consumindo tentativa.
- Fail-closed attempt accounting: falha na persistência do contador de tentativas sob row lock retorna HTTP 503 `unavailable`.
- Validação de client_id inteiro positivo em `issuePasswordReset()` retornando HTTP 422 `invalid_payload` se inválido ou não-positivo.
- Auditoria do proxy reverso Traefik (`coolify-proxy` v3.6): access logging desativado upstream, sem vazamento de tokens. Nginx a jusante com redaction ativa (0 ocorrências de token em claro nos logs).

## S02-A — Histórico de Fechamento [HISTORICAL / CLOSED]

- S02-A is `CLOSED / ACCEPTED`. MapOS canonical `master` was redeployed as `gkpxgzavbwqqiftzfdul9qkj` at `76e72995cf4c00617435aeb34404aa28551533b2`, functionally equivalent to accepted revision `a32ac998b58598f3bab45be0e790c3b46e111592`. S02-A closed all data foundation requirements. Active development transitioned to S03-A (`feature/customer-identity-auth-foundation-s03a`), currently in `TL_ACCEPTANCE`.
- The authoritative runtime is the Orange Pi 5 Pro / Coolify production target, currently authorized by the Client for development/testing without important customer data. Its formal S02 migration completed there at ledger version `20260915120000`.
- The first CLI migration call failed before changing the ledger/schema because `Tools` eagerly required development-only Faker under production Composer `--no-dev`. The narrow lazy seed-runtime correction was committed in `a32ac998b58598f3bab45be0e790c3b46e111592`, redeployed as `oxzoyuklm86rbni20o2axday`, and the single authorized retry passed. No rollback or migration bypass occurred.
- Follow-on UI/auth/OS gate work remains outside S02-A. PHP/runtime validation is performed on Orange Pi, never as a local Windows MapOS runtime.
Last consolidated: 2026-09-17
Source of truth: YES
Scope: MapOS fork TecNina / pendências

---

# Roadmap — MapOS TecNina

## Desenvolvimento

A reorganização administrativa de WhatsApp/pré-atendimentos está implementada e validada localmente.
A bancada administrativa de testes do simulador (`Bot Lab`) em `tecnina_whatsapp/bot_lab` e sua evolução **V2.1** foram implementadas, implantadas e validadas historicamente:
- Resumo de configuração operacional no inspetor do Estado;
- Aba Entregas com inbox de código de registro simulado;
- Links de capability clicáveis seguros gerados via nós do DOM (`document.createElement`);
- Cards estruturados de eventos CAPABILITY na transcrição e passos técnicos no inspetor de Etapas;
- Suíte de 5 testes de contrato PHP aprovados com 129 asserções no total, lints limpos e validação de sintaxe JS limpa.

Estado histórico de validação: implantação operacional anterior em produção (Coolify Deployment #223, baseline `91857c92ec40c54c9d255c8109783164f3d0e848`), validação server-side e humana via browser PASSED. O estado atual do MapOS está no cabeçalho S03-A; Bot Lab pós-Intake requer reconciliação/expansão, não validação inicial.

## Operações pendentes (Pós-deploy V2.1)

- validação visual e via navegador da bancada interativa no MapOS em homologação/produção por operador humano:
  - comportamento do snapshot de configuração operacional;
  - código de registro na aba Entregas;
  - links clicáveis para formulários /p, /g, /c;
  - eventos e passos de capability;
  - invalidação de tokens pós reset e exclusão de sessão;
- confirmação de layout responsivo e ciclo de vida de CSRF no MapOS em execução real;
- estabilização pós-deploy e monitoramento de logs de produção.

## Nova Iniciativa de Desenvolvimento

### AUTOMATED CONVERSATION SCENARIO TESTING (Fase 1 IMPLANTADA EM PRODUÇÃO — VALIDATED)
- **Status de ciclo de vida**: VALIDATED (Deploy de produção realizado; Coolify Deployment #242, baseline `6c119f20bef8d3edba060731f212f0b734e9f0a8`; homologação humana formal PASSED em 21D).
- **Defeito de timeout do gateway de cenários (Scenario Gateway Timeout Defect)**: RESOLVED / DEPLOYED (timeout de execução de cenários configurado em 45s default, bounds 15..90 via `TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS`, sem controle pelo navegador; timeout genérico mantido em 8s).
- **Defeito de renderização da bancada de testes de cenários (Bot Lab Workbench Blank Render Defect)**: RESOLVED / DEPLOYED (hierarquia DOM corrigida no Deployment #239 separando #panel-scenarios-mode como irmão de #panel-interactive-mode).
- **Defeito de payload do Run All (Run All Payload Object Defect)**: RESOLVED / DEPLOYED (Causa raiz: objeto JSON vazio `{}` convertido em array PHP `[]` e re-serializado como `"[]"`, rejeitado com 422 pelo Bot; corrigido com deserialização `stdClass`, sanitização de timeouts e rejeição 422 para arrays top-level no Deployment #242).
- **Validação server-side de cenários automatizados (Server-side Automated Scenario Acceptance)**: PASSED (23/23 cenários aprovados através do gateway deployed).
- **Execução automatizada em navegador real de produção (Real Production Browser Automation)**: PASSED (Playwright Chromium executou "Executar todos" no Bot Lab de produção com 23/23 PASS [0 FAIL / 0 ERROR], duração 13,175 ms (~13.175 s), 0 mutações de negócio no banco e isolamento de segurança validado).
- **Validação humana via browser (Human Browser Acceptance)**: PASSED (homologação humana formal concluída pelo operador em produção via Bot Lab "Executar todos": 23 PASS / 0 FAIL / 0 ERROR; REQ-AST-025 DONE).
- **Escopo e Posse Técnica**:
  - O MapOS é exclusivamente a bancada administrativa e de operação (administrative and operator test workbench).
  - O MapOS possui apenas: apresentação de catálogo/resultados, filtragem/seleção de cenários e proxy server-side.
  - O Bot Gateway possui: schema v1, catálogo sob controle de versão, runner sequencial, especificações YAML, execução isolada em SQLite efêmero, asserções e segurança de resultados.
  - O MapOS **NÃO** reintroduz editor visual de fluxos (Flow Studio), **NÃO** edita arquivos YAML e **NÃO** executa um segundo motor de conversação.
