Status: CURRENT
Last consolidated: 2026-09-12
Source of truth: YES
Scope: MapOS fork TecNina / pendências

---

# Roadmap — MapOS TecNina

## Desenvolvimento

A reorganização administrativa de WhatsApp/pré-atendimentos está implementada e validada localmente.
A bancada administrativa de testes do simulador (`Bot Lab`) em `tecnina_whatsapp/bot_lab` foi **totalmente implementada e validada localmente** (Fase D1), com suite de 5 testes de contrato PHP, lint PHP e JS syntax check aprovados, além do gate local de integração E2E com a API real do Bot.
O código está pronto para publicação de código-fonte (`PUBLISHED / SYNCED`). Implantação e ativação em ambiente operacional permanecem pendentes.

## Pendências operacionais do Bot Lab

As únicas tarefas pendentes relativas ao Bot Lab são:
- deploy coordenado das versões compatíveis de MapOS e Bot;
- habilitação explícita da API do Simulador (`SIMULATOR_ENABLED`) apenas em ambiente autorizado;
- validação visual e via navegador da bancada interativa no MapOS;
- confirmação de layout responsivo e ciclo de vida de CSRF no MapOS em execução real.

*Nota de arquitetura*: O MapOS NÃO possui como responsabilidade a criação de motor de cenários (scenario engine), editor de fluxos (Flow editor) ou novas lógicas de conversação.

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
