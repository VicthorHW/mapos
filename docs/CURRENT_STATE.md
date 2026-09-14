Status: CURRENT
Last consolidated: 2026-09-14
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
- **Status operacional (ADR-009)**: DEPLOYED_UNVERIFIED
- **Estado do código-fonte (Source State)**: PUBLISHED / SYNCED
- **Linha de base de código implantada (Deployed Implementation Baseline)**: `91857c92ec40c54c9d255c8109783164f3d0e848`
- **Implantação operacional (Deployment)**: DEPLOYED (Coolify Deployment #223)
- **Validação server-side**: PASS (rotas proxy do Bot Lab, autorização cSistema, contratos com o Bot Gateway validados no servidor)
- **Validação humana por navegador (Human Browser Acceptance)**: PENDING
- **Operação do V2.1**: Código V2.1 implantado em produção juntamente com Bot V2.1. Backups pré-deploy 20B mantidos no servidor (`/var/backups/tecnina/pre-20b/`).

## Bot Lab Automated Scenario Testing Workbench

- **URL**: `tecnina_whatsapp/bot_lab` (Aba Cenários Automatizados)
- **Papel da funcionalidade**: Bancada de testes e inspeção para cenários declarativos de conversação (`automated scenario test workbench`).
- **Divisão estrita de responsabilidades**:
  - **MapOS possui apenas**: apresentação do catálogo e resultados, UX de seleção e filtragem por tags/texto, e proxy administrativo server-side.
  - **Bot Gateway possui**: schema v1, catálogo de especificações YAML sob controle de versão, runner sequencial, execução isolada em SQLite efêmero, asserções, redaction de segredos e semântica de resultados.
- **O Bot Lab NÃO é**: editor visual de fluxos (Flow Studio), editor de cenários, nem segundo motor de conversação.
- **Status operacional (ADR-009)**: DEPLOYED_UNVERIFIED
- **Implantação operacional (Deployment)**: DEPLOYED (Coolify Deployment #239, baseline `7abedf20d04a6a21d5569ead22bf224eda3fc93a`).
- **Validação server-side**: PASS (Run-all gateway: PASS; 23/23 cenários aprovados através do timeout específico de cenários do gateway implantado em produção; timeout genérico do gateway: 8s; timeout da suíte de cenários: 45s default).
- **Validação humana por navegador (Human Browser Acceptance)**: PENDING (Ordem Técnica 21D: reteste humano pelo operador confirmou abertura do Bot Lab PASS, renderização do catálogo PASS, busca/filtro/seleção PASS, execução selecionada PASS, apresentação de resultados PASS, segurança/rede PASS, console PASS; único bloqueador restante: botão "Executar todos" [Run All] falhava com gateway_request_failed; causa raiz: no controller MapOS Tecnina_whatsapp.php, json_decode($rawPayload, true) perdia a distinção entre objeto vazio {} e array vazio [], serializando como [] para o Bot que rejeitava com HTTP 422; correção IMPLEMENTED_LOCAL preservando stdClass object e sanitizando timeouts; reteste humano: PENDING; ciclo de vida: DEPLOYED_UNVERIFIED).

## Baselines de Produção e Desenvolvimento

### Produção Vigente (Current Production — Automated Scenario Testing Phase 1 & Bot Lab V2.1)
- **Status de ciclo de vida (ADR-009)**: DEPLOYED_UNVERIFIED
- **Linha de base implantada (Deployed Implementation Baseline)**: `7abedf20d04a6a21d5569ead22bf224eda3fc93a` (Coolify Deployment #239)
- **Validação server-side**: PASS (Run-all gateway: PASS; 23/23 cenários aprovados através do timeout específico de cenários do gateway implantado; timeout genérico: 8s, timeout de cenários: 45s default)
- **Aceite humano via browser (Bot Lab)**: PENDING (21D: funções de navegação, catálogo, filtros, seleção e resultados aprovadas pelo operador; bloqueador Run All corrigido localmente [IMPLEMENTED_LOCAL]; reteste humano por operador PENDING)
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

- validação visual e via navegador da bancada interativa V2.1 no MapOS por operador humano:
  - resumo da configuração operacional no Estado (cidades, taxas e agenda presencial);
  - aba Entregas com código de cadastro;
  - abertura de links clicáveis /p, /g, /c;
  - renderização de eventos e passos CAPABILITY;
  - invalidação e limpeza de tokens pós reset e exclusão de sessão;
- confirmação de layout responsivo e ciclo de vida de CSRF no MapOS em execução real;
- estabilização pós-deploy e monitoramento de logs de produção;
- futura iniciativa de testes automatizados de cenários de conversação (AUTOMATED CONVERSATION SCENARIO TESTING).
