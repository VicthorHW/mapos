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

## Governança da mudança e Documentação

Todo agente de IA atuando no MapOS deve seguir rigorosamente a norma [`tecnina-governance/docs/AI_DOCUMENTATION_WORKFLOW.md`](../tecnina-governance/docs/AI_DOCUMENTATION_WORKFLOW.md).

Para qualquer mudança funcional, ajuste ou bugfix:
1. Usar Requirement Ledger e Impact Map quando houver múltiplos requisitos.
2. Na mesma entrega, atualizar: código, testes automatizados, `docs/TECNINA_CUSTOMIZATIONS.md`, `../tecnina-governance/docs/CURRENT_STATE.md` e gerar o respectivo relatório de entrega em `../tecnina-governance/docs/reviews/tech-lead/RELATORIO_ENTREGA_*.md` e despacho `DESPACHO_ACEITE_*.md`.
3. Não implementar a partir de arquivos em `docs/archive/` ou `_handoff/`.
4. Prompts e anexos novos são entradas temporárias: incorporar os requisitos aprovados aos documentos CURRENT/contratos da governança. Pacotes ZIP pertencem a `_handoff/`, nunca à raiz do MapOS ou migrations.

## Git/operação

- não fazer commit/push/deploy sem autorização correspondente do usuário;
- preservar alterações locais pré-existentes não relacionadas;
- executar testes pertinentes e `git diff --check` antes de concluir.
