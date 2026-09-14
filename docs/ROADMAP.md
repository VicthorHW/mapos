Status: CURRENT
Last consolidated: 2026-09-13
Source of truth: YES
Scope: MapOS fork TecNina / pendências

---

# Roadmap — MapOS TecNina

## Desenvolvimento

A reorganização administrativa de WhatsApp/pré-atendimentos está implementada e validada localmente.
A bancada administrativa de testes do simulador (`Bot Lab`) em `tecnina_whatsapp/bot_lab` e sua evolução **V2.1** foram **totalmente implementadas e validadas localmente**:
- Resumo de configuração operacional no inspetor do Estado;
- Aba Entregas com inbox de código de registro simulado;
- Links de capability clicáveis seguros gerados via nós do DOM (`document.createElement`);
- Cards estruturados de eventos CAPABILITY na transcrição e passos técnicos no inspetor de Etapas;
- Suíte de 5 testes de contrato PHP aprovados com 129 asserções no total, lints limpos e validação de sintaxe JS limpa.

Estado do código-fonte: PUBLISHED / SYNCED. Implantação operacional em produção: DEPLOYED (Coolify Deployment #223, baseline `91857c92ec40c54c9d255c8109783164f3d0e848`). Status de ciclo de vida (ADR-009): DEPLOYED_UNVERIFIED. Validação server-side: APROVADA (PASSED). Validação humana via browser: PENDENTE (PENDING).

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

### AUTOMATED CONVERSATION SCENARIO TESTING (Fase 1 IMPLANTADA EM PRODUÇÃO — DEPLOYED_UNVERIFIED)
- **Status de ciclo de vida**: DEPLOYED_UNVERIFIED (Deploy de produção realizado; Coolify Deployment #233, baseline `8e8cd9fc6810ecbe93c64e91a6b8bb4877e6cf10`).
- **Validação server-side**: PASS individual / BLOCKED run-all gateway (23/23 cenários aprovados; execução 'Executar todos' bloqueada pelo timeout de 8s do gateway deployed).
- **Correção técnica local (21C-R1A)**: Suporte a timeout delimitado de 45s (com bounds 15..90 via `TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS`, sem controle pelo navegador) implementado localmente na branch `fix/bot-gateway-scenario-timeout`, pendente de revisão técnica, publicação e deploy.
- **Validação humana via browser**: PENDING (aceite visual do Bot Lab por operador humano pendente).
- **Escopo e Posse Técnica**:
  - O MapOS é exclusivamente a bancada administrativa e de operação (administrative and operator test workbench).
  - O MapOS possui apenas: apresentação de catálogo/resultados, filtragem/seleção de cenários e proxy server-side.
  - O Bot Gateway possui: schema v1, catálogo sob controle de versão, runner sequencial, especificações YAML, execução isolada em SQLite efêmero, asserções e segurança de resultados.
  - O MapOS **NÃO** reintroduz editor visual de fluxos (Flow Studio), **NÃO** edita arquivos YAML e **NÃO** executa um segundo motor de conversação.

## P0 — preparação de release

- executar limpeza controlada de homologação (`TECNINA_TEST_DATA_RESET.md`);

## P1 — validação em ambiente integrado

- deploy MapOS coordenado antes do Gateway;
- executar procedimento pós-deploy vigente;
- validar outbox/trigger e contratos privados;
- validar painel WhatsApp, Cidades com coleta, Pré-atendimentos e Bot Lab;
- validar perfil/revalidação, código de cadastro por e-mail e número reciclado;
- validar credencial recusada/sem senha/texto/padrão e confirmar ausência nos canais do cliente;
- validar oferta e aceite de taxa manual.

## Futuro

- decidir, com backup/migration nova, se algum legado físico pode ser removido;
- manter compatibilidade com upstream e reduzir alterações em arquivos originais sempre que possível.
