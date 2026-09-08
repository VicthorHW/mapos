Status: CURRENT
Last consolidated: 2026-09-08
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
| MAP-06 | Configuração logística/coleta | CURRENT | proxy/admin UI | sem colunas logísticas na OS |
| MAP-07 | Carregamento incremental do painel | CURRENT | `assets/tecnina/js/whatsapp-panel.js` | leitura retry breve; escrita sem retry automático |
| MAP-08 | OS abertas por cliente para consulta atual | IMPLEMENTED_LOCAL / VERIFY | contrato `/api/bot/client/{client_id}/open-os` | confirmar arquivos/teste no repo real |
| MAP-09 | Flow Studio | SUPERSEDED/REMOVED | antiga aba/proxy | não reativar; histórico em archive |
| MAP-10 | Código de 8 caracteres / consulta por OS | SUPERSEDED | rota/status + UI antiga | fluxo atual usa telefone + OS abertas |

## Regra para arquivos upstream

Sempre que uma customização alterar arquivo original do MapOS, registrar:

`arquivo | motivo | por que é necessário | alternativa considerada | teste que protege a alteração`.

O agente com acesso ao repositório deve atualizar esta tabela com os caminhos exatos do endpoint `open-os` após confirmação do código real.

## Regras de manutenção

- minimizar alterações em arquivos upstream;
- preferir controllers/models/libraries TecNina novos;
- preservar contratos privados estáveis;
- rodar instalador em modo de verificação após atualização upstream;
- não colocar WhatsApp/Evolution dentro do MapOS;
- não reintroduzir funcionalidade marcada `SUPERSEDED` porque ainda exista tabela/arquivo histórico.
