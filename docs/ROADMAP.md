Status: CURRENT
Last consolidated: 2026-09-08
Source of truth: YES
Scope: MapOS fork TecNina / pendências

---

# Roadmap — MapOS TecNina

## Desenvolvimento

O escopo funcional CURRENT não possui implementação local pendente conhecida.
Funcionalidades novas exigem change request próprio.

## P0 — preparação de release

- revisar e, mediante autorização, fazer commit/push das alterações locais
  validadas de `CR-20260908-RUNTIME-CLEANUP`;
- executar limpeza controlada de homologação (`TECNINA_TEST_DATA_RESET.md`);

## P1

- deploy MapOS coordenado antes do Gateway;
- executar procedimento pós-deploy vigente;
- validar outbox/trigger e contratos privados;
- validar painel WhatsApp, Cidades com coleta e Pré-atendimentos.

## Futuro

- decidir, com backup/migration nova, se algum legado físico pode ser removido;
- manter compatibilidade com upstream e reduzir alterações em arquivos originais sempre que possível.
