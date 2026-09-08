Status: SUPERSEDED
Source of truth: NO
Original document: `docs/TECNINA_LOGISTICS_LOCATION_LINK.md`
Superseded by: `tecnina-bot/docs/bot-spec/flows/FLOW-04_COLETA_LOCALIZACAO.md`
Archived on: 2026-09-08

> **NÃO IMPLEMENTAR ESTE DOCUMENTO.** O link novo vigente usa `/g/{token}` e não um fluxo visual antigo. Mantido somente para histórico, evidência e rastreabilidade.

---

# Link temporario de localizacao

O painel `Configuracoes -> WhatsApp -> Logistica` pode emitir um link
temporario para um appointment. O MapOS apenas encaminha a requisicao interna
autenticada ao Gateway e apresenta a URL retornada; nao persiste o token,
coordenadas ou endereco no banco MapOS.

O link usa a forma `https://bot-api.tecnina.com/l#token`. O fragmento nao e
enviado pelo navegador ao servidor, proxy ou logs de acesso. A pagina publica
envia a capacidade somente no corpo dos POSTs HTTPS para capturar e confirmar
o local. O token expira, pode ser substituido por novo link e nao atualiza
automaticamente o endereco cadastral do cliente.

O recurso e acessivel somente a operadores com `cSistema`. Compartilhe o link
somente com o cliente daquele atendimento.

## Nota operacional do deploy

Em setembro de 2026, a imagem flutuante `mysql:8.4` resolveu para 8.4.11 e
encerrou `mysqld` com SIGSEGV durante o startup do InnoDB na Orange Pi. A
imagem oficial 8.4.10 foi validada em container temporario sem volume no mesmo
host e completou o startup. O compose fixa 8.4.10 por padrao para impedir que
um novo deploy troque a versao sem validacao. Antes de subir uma patch release,
validar a imagem no host e revisar a compatibilidade do volume persistente.
