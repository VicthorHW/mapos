# Customizações TecNina

Inventário das diferenças mantidas pela fork para facilitar comparação e
reaplicação após atualizações do MapOS upstream.

## Integração WhatsApp — Fase 3

### Arquivos upstream alterados

| Arquivo | Motivo | Necessidade | Alternativa avaliada |
| --- | --- | --- | --- |
| `application/.env.example` | Documentar o token interno e TTL do claim | Evitar configuração implícita ou secret versionado | Documentação separada não informa novas instalações durante o setup |
| `composer.json` | Incluir o teste estrutural na suíte padrão | Impedir regressão silenciosa no CI | Executar manualmente; rejeitado por ser fácil esquecer |
| `tools/device-credential/post-deploy.sh` | Encadear a outbox no lifecycle já configurado | Garantir atualização automática da instalação existente | Alteração manual imediata no Coolify; mantido apenas como ponte de compatibilidade |

Nenhum controller ou model existente do MapOS foi alterado. A captura de todas
as mudanças de status é feita pela trigger MySQL instalada separadamente.

### Arquivos novos TecNina

- `application/controllers/Tecnina_integration_setup.php`
- `application/controllers/api/bot/Health.php`
- `application/controllers/api/bot/Outbox.php`
- `application/libraries/Tecnina_bot_auth.php`
- `application/models/Tecnina_outbox_model.php`
- `tools/tecnina-integration/install.php`
- `tools/tecnina-integration/post-deploy.sh`
- `tools/tecnina-post-deploy.sh`
- `tests/TecninaOutboxTest.php`
- `docs/TECNINA_OUTBOX.md`

## Regras de manutenção

- manter o Gateway sem acesso direto ao banco do MapOS;
- não inserir chamadas ao WhatsApp nos controllers de OS;
- executar o instalador com `--verify-only` depois de atualizar o upstream;
- executar testes de contrato antes de habilitar o dispatcher;
- registrar nesta lista qualquer novo arquivo upstream alterado.
