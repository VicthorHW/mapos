Status: CURRENT
Last consolidated: 2026-09-09
Source of truth: YES
Scope: MapOS fork TecNina / pendências

---

# Roadmap — MapOS TecNina

## Desenvolvimento

A reorganização administrativa de WhatsApp/pré-atendimentos está implementada e
validada localmente; aguarda deploy/E2E coordenado. Funcionalidades novas exigem
change request próprio.

A prévia administrativa de GPS está implementada e deve ser validada visualmente
com um intake real após o próximo deploy coordenado.

Os contratos de perfil/cadastro, a aprovação com credencial e a oferta de taxa
manual estão implementados localmente e devem seguir no mesmo release coordenado
do Gateway `20260908_0019`.

## P0 — preparação de release

- executar limpeza controlada de homologação (`TECNINA_TEST_DATA_RESET.md`);

## P1

- deploy MapOS coordenado antes do Gateway;
- executar procedimento pós-deploy vigente;
- validar outbox/trigger e contratos privados;
- validar painel WhatsApp, Cidades com coleta e Pré-atendimentos.
- validar perfil/revalidação, código de cadastro por e-mail e número reciclado;
- validar credencial recusada/sem senha/texto/padrão e confirmar ausência nos
  canais do cliente;
- validar oferta e aceite de taxa manual.

## Futuro

- decidir, com backup/migration nova, se algum legado físico pode ser removido;
- manter compatibilidade com upstream e reduzir alterações em arquivos originais sempre que possível.
