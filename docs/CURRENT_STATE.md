Status: CURRENT
Last consolidated: 2026-09-08
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
- painel `Configurações → WhatsApp` protegido por `cSistema`;
- revisão/aprovação de intake via proxy server-side;
- criação idempotente de cliente/OS na aprovação;
- configuração logística/coleta administrada sem colocar estados logísticos dentro da OS;
- painel carrega abas de forma incremental;
- Flow Studio removido da UI/proxy;
- consulta vigente de reparo deve suportar cliente identificado pelo telefone e suas OS abertas via contrato mínimo.

## Estado do snapshot

Branch `master`, HEAD `d9ff0a0`, com alterações funcionais locais ainda não
commitadas. Os 13 scripts do `composer test`, `php -l` do controller alterado,
`node --check` do painel e `git diff --check` foram aprovados localmente para
`CR-20260908-RUNTIME-CLEANUP`.

## Confirmações no repositório real

- `GET /api/bot/client/{client_id}/open-os` está implementado por `application/controllers/api/bot/Client_open_os.php` e `application/models/Tecnina_client_open_os_model.php`;
- a rota está registrada em `application/config/routes.php`;
- controller e model constam no instalador `tools/tecnina-integration/install.php`;
- `tests/TecninaIntakeApprovalTest.php` verifica rota e instalação;
- o mecanismo antigo de código de oito caracteres não está exposto pelo painel; endpoints residuais retornam desativação explícita;
- a UI e o proxy do Flow Studio estão removidos; testes de regressão cobrem essa ausência.
- o painel não oferece emissão manual do link legado de localização; a coleta é
  iniciada automaticamente pelo fluxo vigente do Gateway em `/g/{token}`.

## Legado que não deve orientar novas mudanças

- Flow Studio: removido; tabelas históricas no banco do Gateway podem ficar inertes.
- consulta por código de 8 caracteres: desenho anterior, não é o fluxo conversacional atual.
- documentos de link `/l`/mapa: substituídos pelo contrato atual do Gateway.

## Próxima validação necessária

- limpar dados de teste do MapOS de forma controlada;
- deploy antes do Gateway;
- validar contratos `/api/bot/*`, outbox, painel, intake e notificações em ambiente real.

O escopo funcional CURRENT não possui implementação local pendente conhecida;
os itens acima pertencem à preparação de release e à validação operacional.
