Status: REFERENCE
Last consolidated: 2026-09-08
Source of truth: NO
Scope: MapOS TecNina / limpeza de homologação

> Este arquivo é referência técnica. Em conflito com uma fonte de verdade CURRENT, a fonte CURRENT prevalece.

---

# Limpeza controlada dos dados de teste

Objetivo atual: antes do uso real, remover OS e financeiro exclusivamente de teste, excluir `Cliente teste 2` e fazer a próxima OS voltar a 1, preservando configurações e cadastros úteis.

## Proibições

- não usar `DROP DATABASE`/`DROP TABLE`;
- não executar `TRUNCATE` indiscriminado;
- não desligar `FOREIGN_KEY_CHECKS` para forçar exclusão;
- não apagar lançamentos potencialmente reais;
- não apagar cobrança externa ativa apenas porque a linha local é de teste;
- não alterar estoque sem reproduzir a semântica do MapOS.

## Procedimento

1. descobrir container/banco efetivo e schema real;
2. inventariar FKs para `os`, `clientes`, `lancamentos`, `vendas` e tabelas TecNina;
3. identificar ID exato de `Cliente teste 2`;
4. registrar contagens, min/max OS, `AUTO_INCREMENT`, lançamentos, receitas/despesas, cobranças/vendas;
5. criar dump completo verificável (`--single-transaction` quando aplicável);
6. verificar `produtos_os` e comportamento de devolução de estoque do código vigente;
7. verificar cobranças/gateways externos;
8. remover dependências e dados inequivocamente de teste na ordem das FKs;
9. excluir OS;
10. excluir `Cliente teste 2` após todas as referências permitidas terem sido removidas;
11. somente com `COUNT(os)=0`, executar operação equivalente a `ALTER TABLE os AUTO_INCREMENT = 1`;
12. validar integridade, financeiro zerado de testes e preservação de usuários/configurações/produtos/serviços/termos.

## Saída obrigatória da execução

Backup criado, tabelas afetadas, registros removidos por tabela, ajustes de estoque, cliente removido, auto_increment antes/depois, validação de FKs, financeiro final e qualquer dado preservado por não ser inequivocamente teste.
