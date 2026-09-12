Status: CURRENT
Last consolidated: 2026-09-12
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

## Bot Lab (Bancada Administrativa do Simulador)

- **URL**: `tecnina_whatsapp/bot_lab`
- **Permissão de acesso**: protegida estritamente por `cSistema`.
- **Segurança do navegador**: autenticação por sessão do MapOS + token CSRF do CodeIgniter a cada requisição mutadora. O navegador NUNCA recebe o `MAPOS_BOT_TOKEN` e NUNCA faz chamadas diretas às rotas `/admin/simulator/*` do Bot.
- **Proxy Server-Side**: o controller `Tecnina_whatsapp.php` faz o papel de proxy seguro no backend, e a biblioteca `Tecnina_bot_gateway.php` efetua as chamadas HTTP server-to-server com bearer token.
- **Fonte da verdade**: a sessão de simulação no Bot é a única fonte da verdade para o estado da conversa, histórico e efeitos. O MapOS NÃO armazena estado conversacional nem histórico de simulação independente.
- **Papel da funcionalidade**: o Bot Lab é exclusivamente uma bancada administrativa de testes interativos (`admin test workbench`). NÃO é Flow Studio, NÃO é editor de FSM, NÃO é autoria de workflows, NÃO é um segundo motor de conversação e NÃO é visualizador de conversas de produção.
- **Capacidades da UI**: setup de fixtures, console de envio de mensagens e localização, visualizador de estado do runtime, inspetor do ledger de passos e inspetor de efeitos externos observáveis. Nenhum executor de cenários existe na implementação.
- **Status da entrega**: `IMPLEMENTED_LOCAL` / pronto para publicação de código-fonte (`ready for source publication / not deployed`).

## Estado do snapshot

Branch `feature/bot-lab` consolidado em 2026-09-12.
- 5 testes de contrato PHP executados com 93 asserções (incluindo 33 asserções em `TecninaBotLabPanelTest.php`);
- lints de PHP limpos;
- validação de sintaxe JavaScript limpa (`node --check assets/tecnina/js/bot-lab.js`);
- validação E2E local cruzada entre `Tecnina_bot_gateway` e a Admin API real do Bot executada com 45 asserções e 0 falhas.

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

- deploy coordenado das versões compatíveis de MapOS e Bot;
- habilitação explícita de `SIMULATOR_ENABLED` apenas em ambiente autorizado;
- validação visual e via navegador do Bot Lab no MapOS;
- confirmação de layout responsivo e ciclo de vida de CSRF no MapOS em execução real.
