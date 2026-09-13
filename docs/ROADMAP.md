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

Estado do código-fonte: PUBLISHED / SYNCED (publicado no branch `master` em `89356133ec7327eb4ff81113c1558162273f52cb`). Implantação operacional e validação em ambiente de produção: PENDENTES (NOT DEPLOYED / NOT PERFORMED).

## Pendências operacionais do Bot Lab V2.1

As únicas tarefas pendentes relativas ao Bot Lab V2.1 são:
- deploy coordenado das versões compatíveis de MapOS V2.1 e Bot V2.1;
- validação visual e via navegador da bancada interativa no MapOS em homologação/produção:
  - comportamento do snapshot de configuração operacional;
  - código de registro na aba Entregas;
  - links clicáveis para formulários /p, /g, /c;
  - eventos e passos de capability;
  - invalidação de tokens pós reset e exclusão de sessão;
- confirmação de layout responsivo e ciclo de vida de CSRF no MapOS em execução real.

*Nota de arquitetura*: O suporte a links clicáveis de capabilities foi implementado na V2.1 e não é mais item futuro. O MapOS NÃO possui como responsabilidade a criação de motor de cenários (scenario engine), editor de fluxos (Flow editor) ou novas lógicas de conversação.

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
