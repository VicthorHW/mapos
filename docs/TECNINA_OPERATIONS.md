Status: CURRENT
Last consolidated: 2026-09-08
Source of truth: YES
Scope: MapOS TecNina / operação

---

# Operação do MapOS na integração TecNina

## Antes de deploy

- revisar `git status`/diff;
- executar suíte TecNina do `composer test` ou equivalente vigente;
- `node --check` no JS alterado;
- `php -l` em controllers/views alterados;
- `git diff --check`;
- backup antes de qualquer alteração destrutiva de dados.

## Ordem coordenada

Quando houver mudança de contrato MapOS↔Gateway, publicar primeiro o MapOS, validar o endpoint privado e só então publicar o Gateway dependente.

## Painel WhatsApp

Validar:

- autorização `cSistema`;
- Visão geral/Conversas;
- Pré-atendimentos e aprovação;
- Logística/Cidades com coleta;
- Fila/Logs/Regras/Templates/Configuração conforme versão vigente;
- ausência da aba Flow Studio;
- ausência de controles do código antigo de consulta.

## Dados de teste

Usar `TECNINA_TEST_DATA_RESET.md`; não apagar em SQL sem entender estoque, financeiro, cobranças e FKs.
