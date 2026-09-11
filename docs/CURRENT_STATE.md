Status: CURRENT
Last consolidated: 2026-09-09
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
- pré-atendimentos em área operacional própria no menu, com revisão/aprovação via
  proxy server-side;
- criação idempotente de cliente/OS na aprovação;
- configuração logística/coleta administrada sem colocar estados logísticos dentro da OS;
- painel carrega áreas de forma incremental e consolida conexão/fila e mensagens
  automáticas;
- Flow Studio removido da UI/proxy;
- consulta vigente de reparo deve suportar cliente identificado pelo telefone e suas OS abertas via contrato mínimo.
- contratos privados aditivos para perfil mínimo, atualização cadastral,
  desvinculação segura de telefone, criação opcional de cliente e entrega de
  código por e-mail;
- aprovação de intake aceita credencial opcional e a grava pela biblioteca
  protegida já usada nas OS, sem copiá-la para observações;
- painel de pré-atendimentos permite ao operador oferecer taxa manual de coleta.
- a interface de pré-atendimentos exibe a origem do GPS (WhatsApp ou navegador) e os rótulos de status para o gate da taxa manual ("Aguardando envio da taxa").
- o payload de aprovação do pré-atendimento (`Intake_approval.php`) foi atualizado para reconhecer a confirmação de taxa via WhatsApp (`ACCEPTED`) como estado de liberação válido.
- a biblioteca `Tecnina_phone.php` agora suporta números de telefone internacionais, deixando de restringir a comunicação apenas a números brasileiros.

## Estado do snapshot

Branch `master`, base desta entrega `d847d66`, com a reorganização administrativa registrada em
`CR-20260908-ADMIN-PREATTENDANCE-REORGANIZATION` incluída nesta entrega e ainda
sem deploy/E2E.
Os 16 scripts PHP de regressão foram aprovados, assim como lint dos arquivos PHP
alterados e `node --check` dos três painéis JavaScript ativos.

## Confirmações no repositório real

- `GET /api/bot/client/{client_id}/open-os` está implementado por `application/controllers/api/bot/Client_open_os.php` e `application/models/Tecnina_client_open_os_model.php`;
- a rota está registrada em `application/config/routes.php`;
- controller e model constam no instalador `tools/tecnina-integration/install.php`;
- `tests/TecninaIntakeApprovalTest.php` verifica rota e instalação;
- o mecanismo antigo de código de oito caracteres não está exposto pelo painel; endpoints residuais retornam desativação explícita;
- a UI e o proxy do Flow Studio estão removidos; testes de regressão cobrem essa ausência.
- o painel não oferece emissão manual do link legado de localização; a coleta é
  iniciada automaticamente pelo fluxo vigente do Gateway em `/g/{token}`.
- `os.observacoes` é visível no portal/PDF/e-mail, enquanto `anotacoes_os` não é
  consultada por esses canais; metadados do intake passam a usar Anotações.
- endereço de coleta não altera o endereço cadastral de cliente existente nem é
  copiado silenciosamente ao criar cliente novo.
- revisão de pré-atendimento apresenta GPS em mapa OpenStreetMap e oferece link
  de coordenadas para o Google Maps, sem API paga.

## Legado que não deve orientar novas mudanças

- Flow Studio: removido; tabelas históricas no banco do Gateway podem ficar inertes.
- consulta por código de 8 caracteres: desenho anterior, não é o fluxo conversacional atual.
- documentos de link `/l`/mapa: substituídos pelo contrato atual do Gateway.

## Próxima validação necessária

- limpar dados de teste do MapOS de forma controlada;
- deploy antes do Gateway;
- validar contratos `/api/bot/*`, outbox, painel, intake e notificações em ambiente real.

O escopo funcional CURRENT não possui implementação funcional local pendente
conhecida; os itens acima pertencem à preparação de release e à validação
operacional.
