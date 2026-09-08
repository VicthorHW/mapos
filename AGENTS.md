Status: CURRENT
Last consolidated: 2026-09-08
Source of truth: YES
Scope: MapOS fork TecNina / regras do agente

---

# AGENTS.md — MapOS TecNina

## Escopo

Este repositório é a fork MapOS usada pela TecNina. Preservar compatibilidade com upstream e manter customizações TecNina isoladas, documentadas e testáveis.

## Regras arquiteturais

- MapOS continua responsável por clientes, OS, financeiro e dados operacionais próprios.
- Gateway não recebe acesso SQL ao banco MapOS; expor somente contratos mínimos `/api/bot/*`.
- Não inserir chamadas diretas à Evolution/WhatsApp em controllers de OS.
- Toda alteração upstream mantida pela TecNina deve entrar em `docs/TECNINA_CUSTOMIZATIONS.md`.
- Não reutilizar API administrativa ampla quando um contrato mínimo privado puder resolver.

## Banco e migrations

- não desligar `FOREIGN_KEY_CHECKS` como atalho;
- não reescrever migration/instalador já aplicado para apagar histórico;
- alterações destrutivas exigem backup, análise de FKs e autorização explícita;
- limpeza de dados de teste deve seguir `docs/TECNINA_TEST_DATA_RESET.md`;
- não usar `DROP DATABASE`, `migrate:fresh` ou `TRUNCATE` indiscriminadamente em ambiente com dados persistentes.

## Segurança

- nunca versionar secrets, tokens, senhas, CPFs ou payloads reais;
- token MapOS↔Gateway permanece server-side;
- browser do painel não recebe bearer interno, JID completo ou dados privados desnecessários;
- endpoints do Bot usam whitelist explícita.

## Governança da mudança

Para mudança funcional com múltiplos requisitos, use Requirement Ledger e Impact Map. Na mesma entrega, atualizar quando afetados: código, testes, `docs/TECNINA_CUSTOMIZATIONS.md`, `docs/CURRENT_STATE.md`, `docs/ROADMAP.md` e contratos.

Não implementar a partir de arquivos em `docs/archive/`.

Prompts e anexos novos são entradas temporárias: incorporar os requisitos aprovados aos documentos CURRENT/contratos e, depois, mover o original para `docs/archive/requests/`. Pacotes ZIP pertencem a `docs/archive/packages/`, nunca à raiz, migrations ou documentação principal.

## Git/operação

- não fazer commit/push/deploy sem autorização correspondente do usuário;
- preservar alterações locais pré-existentes não relacionadas;
- executar testes pertinentes e `git diff --check` antes de concluir.
