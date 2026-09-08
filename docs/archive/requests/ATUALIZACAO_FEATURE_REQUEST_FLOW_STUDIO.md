Status: SUPERSEDED
Last consolidated: 2026-09-08
Source of truth: NO
Superseded by: remoção do Flow Studio e especificação normativa em `tecnina-bot/docs/bot-spec/`

> **NÃO IMPLEMENTAR ESTE DOCUMENTO.** Esta solicitação foi substituída pela FSM determinística e pela remoção do Flow Studio. É mantida apenas para rastreabilidade.

---

# ATUALIZAÇÃO DO FEATURE REQUEST — FLOW STUDIO / EDITOR VISUAL DOS FLUXOS DO BOT

Leia integralmente o `FEATURE_REQUEST_TECNINA_WHATSAPP_MAPOS.md` e inspecione a implementação atual antes de modificar a documentação.

Esta solicitação é, neste momento, **EXCLUSIVAMENTE DOCUMENTAL**.

**NÃO** implementar o editor visual agora.

O objetivo é adicionar ao Feature Request uma nova capacidade arquitetural:
um painel visual de fluxos do TecNina Bot Gateway dentro da área de WhatsApp do MapOS.

A intenção não é criar apenas um diagrama ilustrativo.

O objetivo de longo prazo é que exista uma representação visual, versionada, validável e parcialmente editável da lógica conversacional realmente executada pelo Gateway.

Essa funcionalidade será chamada conceitualmente neste documento de:

**TECNINA FLOW STUDIO**

ou:

**BOT FLOW STUDIO**

O nome final de UI poderá ser simplesmente:

**Fluxos**

============================================================
## 1. MOTIVAÇÃO
============================================================

O Bot já possui múltiplos componentes:

- menu inicial;
- identificação de intenção;
- pré-atendimento;
- cliente existente / desconhecido / ambíguo;
- consulta MapOS;
- Human Lock;
- fallback;
- horário de atendimento;
- atualização de status;
- templates;
- localização/logística futuramente;
- tratamento de falhas;
- feature flags.

À medida que essas regras crescem, compreender todo o comportamento apenas lendo código se torna difícil.

É necessário permitir ao operador/desenvolvedor visualizar claramente:

entrada
→ condições
→ mensagem
→ decisão
→ próxima etapa
→ fallback
→ atendimento
→ término

O Flow Studio deve melhorar:

- manutenção;
- entendimento;
- testes;
- debugging;
- edição de mensagens;
- ativação/desativação de funcionalidades;
- evolução futura do Bot;
- possível transformação do Gateway em um produto reutilizável.

============================================================
## 2. PRINCÍPIO ARQUITETURAL
============================================================

**NÃO** transformar o Bot em um engine arbitrário de automação no qual o operador possa escrever código.

O Flow Studio deve ser **DECLARATIVO** e **RESTRITO**.

Não permitir dentro do editor:

- Python;
- PHP;
- JavaScript arbitrário;
- SQL;
- shell;
- expressões eval;
- URLs HTTP arbitrárias;
- acesso direto ao banco;
- chamadas arbitrárias à Evolution;
- chamadas arbitrárias ao MapOS.

O editor deverá utilizar somente:

NODE TYPES permitidos
+
ACTIONS permitidas
+
CONDITIONS permitidas
+
TEMPLATES permitidos.

A execução continua pertencendo ao TecNina Bot Gateway.

O MapOS funciona apenas como interface administrativa.

============================================================
## 3. NÃO REESCREVER A FSM ATUAL IMEDIATAMENTE
============================================================

A implementação atual já possui FSM, Intake, Human Lock e controles validados.

**NÃO** substituir toda a FSM por um graph engine em uma única alteração.

Planejar migração incremental.

Etapas conceituais:

1. visualizar fluxo atual;
2. tornar mensagens/configurações editáveis;
3. tornar condições simples declarativas;
4. migrar gradualmente partes da FSM para FlowDefinition;
5. somente depois permitir edição estrutural mais ampla.

A primeira versão do Flow Studio pode ser majoritariamente **READ-ONLY** quanto à estrutura, mas já permitir:

- edição de mensagens;
- enable/disable de caminhos explicitamente suportados;
- edição de parâmetros;
- simulação;
- visualização do estado atual.

Não sacrificar a robustez atual para obter drag-and-drop prematuramente.

============================================================
## 4. TIPOS DE FLUXO
============================================================

Não colocar todo o Bot em um único fluxograma gigante.

Separar flows por responsabilidade.

Inicialmente prever pelo menos:

**customer_service_main**
→ atendimento automático principal.

**intake**
→ pré-atendimento.

**status_notifications**
→ notificações transacionais de OS.

Futuramente:

**logistics**
→ coleta/entrega/localização.

**verification**
→ autenticação/verificação avançada.

Cada fluxo deverá possuir:

- key
- name
- description
- flow_type
- enabled
- draft version
- published version
- created_at
- updated_at

============================================================
## 5. FLUXO PRINCIPAL DE ATENDIMENTO
============================================================

O fluxo visual mais importante será:

**ATENDIMENTO AUTOMÁTICO**

Exemplo conceitual:

```text
INBOUND MESSAGE
      |
      v
É GRUPO?
  |       |
 SIM     NÃO
  |       |
IGNORE    v
       HUMAN LOCK?
        |       |
       SIM     NÃO
        |       |
      SILENT    v
             SESSÃO ATIVA?
              |       |
             SIM     NÃO
              |       |
          continuar   v
                    IDENTIFICAR
                     INTENÇÃO
                 /      |      \
              REPARO  ASSIST.  FALLBACK
                |        |        |
                v        v        v
             consulta   intake    menu
```

Esse exemplo é apenas conceitual.

O editor deve refletir o fluxo real implementado, não copiar cegamente este desenho.

============================================================
## 6. CONDIÇÕES IMPORTANTES QUE DEVEM SER VISÍVEIS
============================================================

Prever representação visual de condições como:

- grupo / conversa individual;
- Human Lock ativo;
- Bot habilitado;
- Intake habilitado;
- auto replies habilitado;
- sessão/draft ativo;
- cliente inexistente;
- cliente único;
- cliente ambíguo;
- MapOS disponível;
- MapOS indisponível;
- horário de atendimento aberto;
- horário de atendimento fechado;
- intenção reconhecida;
- intenção ambígua;
- fallback_count;
- modalidade TRAZER;
- modalidade COLETA;
- localização necessária;
- localização confirmada;
- sessão verificada;
- nível de autenticação;
- status conhecido;
- regra de status habilitada;
- configuração de privacidade;
- feature flag.

**NÃO** permitir ao usuário escrever condições SQL ou código.

Usar um registry de condições suportadas pelo Gateway.

============================================================
## 7. NODE TYPES
============================================================

Prever um conjunto limitado de tipos de nó.

Exemplo conceitual:

START
MESSAGE
MENU
INPUT
CONDITION
ACTION
SUBFLOW
HANDOFF
WAIT_INPUT
END
ERROR / FALLBACK

Os nomes finais podem mudar.

**START**
Ponto inicial do fluxo.
Um flow deve possuir apenas um START válido.

**MESSAGE**
Envia mensagem utilizando Template/MessageDefinition.
Não armazenar necessariamente todo texto dentro do graph.
Preferir referência:
`template_key`
Exemplo:
`bot.welcome`

**MENU**
Mostra opções e espera entrada.
Exemplo:
1 — Acompanhar reparo
2 — Solicitar assistência
3 — Conhecer serviços
4 — Falar com atendimento

Deve possuir:
- opções;
- aliases;
- fallback edge;
- next nodes.

**INPUT**
Coleta um dado.
Exemplo:
- name
- device_type
- brand
- model
- problem_description

Configurações possíveis:
- field
- required
- parser
- validation
- retry/fallback
- next_node

**CONDITION**
Avalia estado seguro conhecido pelo Gateway.
Exemplo:
`customer_match == UNIQUE`

Saídas:
TRUE
FALSE

ou enum:
NONE
UNIQUE
AMBIGUOUS

**ACTION**
Executa apenas ação registrada e whitelisted.
Exemplos:
- START_INTAKE
- CANCEL_INTAKE
- LOOKUP_CLIENT
- LOOKUP_OS
- RESET_FALLBACK
- SET_SESSION_STATE
- REQUEST_LOCATION
- ACTIVATE_HUMAN_LOCK
- CLEAR_FLOW_STATE

Nenhuma ACTION arbitrária.

**SUBFLOW**
Chama outro flow.
Exemplo:
`customer_service_main` → `intake`
ou:
`customer_service_main` → `status_query`

Isso evita um gráfico gigantesco.

**HANDOFF**
Encaminha para Atendimento.
Deve respeitar:
- Business Hours;
- Human Lock;
- templates;
- audit trail.

**WAIT_INPUT**
Ponto no qual o fluxo para até nova mensagem/evento.
Isso é importante para impedir loops síncronos.

**END**
Finaliza fluxo/sessão conforme regra explícita.

============================================================
## 8. ZERO-WAIT LOOP PROTECTION
============================================================

Grafos podem possuir ciclos.

Exemplo válido:
MENU
→ resposta inválida
→ FALLBACK
→ MENU

Porém o runtime nunca deve entrar em:
CONDITION
→ ACTION
→ CONDITION
→ ACTION
→ ...
infinitamente dentro da mesma mensagem.

Implementar no futuro validação de:
**ZERO-WAIT CYCLE**

Ou seja:
todo ciclo de conversa deve passar por:
**WAIT_INPUT**
ou outro boundary assíncrono real.

O runtime também deverá possuir:
`max_steps_per_event`
como fail-safe.

============================================================
## 9. FLOW DEFINITIONS VERSIONADOS
============================================================

Nunca editar diretamente o flow atualmente publicado.

Usar:
- DRAFT
- PUBLISHED
- ARCHIVED

Exemplo:
Atendimento automático

Published:
v12

Draft:
v13

O operador altera v13 sem afetar clientes.

Depois:
VALIDAR
→ TESTAR
→ PUBLICAR.

A versão publicada anterior permanece disponível para rollback.

============================================================
## 10. VERSÕES PUBLICADAS DEVEM SER IMUTÁVEIS
============================================================

Uma versão PUBLISHED não deve ser modificada in-place.

Editar:
v12 PUBLISHED

gera:
v13 DRAFT.

Publicação deve ser atômica.

Nunca deixar flow parcialmente publicado.

Guardar:
- published_at
- published_by
- version
- hash/checksum
- changelog opcional

============================================================
## 11. ROLLBACK
============================================================

Permitir:
v15 atual
↓
rollback
↓
v14

Rollback deve criar/publicar uma versão coerente, preservando histórico.

Não apagar versões antigas para realizar rollback.

============================================================
## 12. CONVERSA PINADA À VERSÃO
============================================================

Problema:
cliente começa Intake utilizando v12.
No meio da conversa o operador publica v13.

**NÃO** mudar silenciosamente a estrutura enquanto o cliente está no estágio:
WAITING_MODEL

Regra preferencial:
uma sessão iniciada em determinado flow_version permanece pinada nessa versão até atingir boundary seguro.

Novas sessões usam a última versão publicada.

Prever operação administrativa explícita:
Migrate session to latest version
somente quando seguro.

============================================================
## 13. SESSÃO DE CONVERSA NO FLOW STUDIO
============================================================

Ao abrir uma conversa no painel, permitir visualizar:

- Flow: customer_service_main
- Version: 12
- Current node: WAITING_DEVICE_MODEL
- State: ACTIVE
- Fallback count: 0
- Human Lock: OFF
- Intake: #45
- Customer match: UNIQUE
- Business hours: CLOSED
- Verification: UNVERIFIED

Não exibir dados sensíveis desnecessários.

============================================================
## 14. DESTACAR O NÓ ATUAL
============================================================

Uma das funcionalidades mais importantes para debugging:
abrir conversa
→ abrir fluxo
→ destacar visualmente o nó atual.

Exemplo:

```text
[ START ]
|
v
[ MENU ]
|
v
[ START INTAKE ]
|
v
[ DEVICE ]
|
v

[ WAITING BRAND ] <<<
```

Isso deve permitir entender imediatamente:
"onde o cliente está parado?"

============================================================
## 15. EXECUTION TRACE
============================================================

Guardar ou gerar trace operacional sanitizado.

Exemplo:
- 13:42:01 inbound_received
- 13:42:01 human_lock=false
- 13:42:01 active_intake=true
- 13:42:01 current_node=WAITING_BRAND
- 13:42:01 parser=free_text
- 13:42:01 transition=WAITING_MODEL

Não guardar payload sensível por conveniência.

A interface pode mostrar:
Mensagem recebida
→ condição avaliada
→ resultado
→ node escolhido.

============================================================
## 16. EXPLICAR POR QUE UM CAMINHO FOI ESCOLHIDO
============================================================

No debug:

CONDITION
Cliente encontrado?

Resultado:
UNIQUE

Porque:
MapOSAdapter.findClient() retornou match=unique

Transição:
EXISTING_CUSTOMER_MENU

Não mostrar:
SQL
telefone completo
token
credencial.

============================================================
## 17. SIMULADOR DE FLUXO
============================================================

Adicionar uma ferramenta:
**TESTAR FLUXO**

Ela deve funcionar sem enviar WhatsApp real por padrão.

Permitir iniciar:

Cliente:
"oi"

Simulator:
→ START
→ MENU

Operador digita:
"meu notebook não liga"

Simulator:
→ intent_assistance
→ intake

Mostrar graficamente cada transição.

============================================================
## 18. CENÁRIOS DE TESTE PRÉ-DEFINIDOS
============================================================

Permitir selecionar cenários.

Exemplos:
- NOVO CLIENTE
- CLIENTE EXISTENTE
- CLIENTE AMBÍGUO
- MAPOS OFFLINE
- DENTRO DO HORÁRIO
- FORA DO HORÁRIO
- HUMAN LOCK
- INTAKE EXISTENTE
- FALLBACK 1
- FALLBACK 2
- FALLBACK 3
- COLETA
- TRAZER EQUIPAMENTO
- LOCALIZAÇÃO PENDENTE
- OS ÚNICA
- MÚLTIPLAS OS

Os valores devem ser fictícios.

============================================================
## 19. TEST MODE COM NÚMERO REAL CONTROLADO
============================================================

Futuramente permitir testar um DRAFT apenas com número de teste explicitamente allowlisted.

Exemplo:
Published: v12
Draft: v13

Número normal:
→ v12

Número de teste autorizado:
→ v13

Isso permite testar alterações reais pelo WhatsApp antes da publicação.

Requer:
- confirmação explícita;
- allowlist;
- audit log;
- indicação visual clara de TEST MODE.

Não permitir que cliente normal entre acidentalmente no Draft.

============================================================
## 20. MENSAGENS EDITÁVEIS NO NÓ
============================================================

Ao clicar em MESSAGE:
abrir inspector lateral.

Exibir:
Template: bot.welcome
Versão atual: 8
Texto:
[...]

[ Editar ]

A edição deve reutilizar o sistema de templates versionados já existente, sempre que tecnicamente compatível.
Não criar um segundo banco de mensagens.

============================================================
## 21. PREVIEW DA MENSAGEM
============================================================

Inspector deve possuir:
PREVIEW WHATSAPP
usando dados fictícios.

Mostrar aproximadamente:
formatação
negrito
quebra de linha
emoji
placeholders.

Não enviar WhatsApp para fazer preview.

============================================================
## 22. REGRAS DE COPY PARA ATENDIMENTO
============================================================

Incorporar como regra global de linguagem:

Evitar:
"Atendimento humano"

Preferir apenas:
"Atendimento"

Dentro do horário:
"Responderemos assim que possível."

Fora do horário:
"Agora estamos fora do horário de atendimento. Sua mensagem ficou registrada e será revisada assim que possível."

**NÃO** informar automaticamente:
- próximo horário;
- "amanhã";
- "segunda-feira às 9h";
- próxima janela.

Somente informar horários se o cliente solicitar.
Nesse caso, mostrar a tabela configurada de horários.
Também não transformar horário em SLA.

============================================================
## 23. BUSINESS HOURS COMO CONDITION NODE
============================================================

Exemplo:

```text
[ FALAR COM ATENDIMENTO ]
|
v
[ ATENDIMENTO ABERTO? ]
/             \
SIM             NÃO
|               |
v               v
[MSG OPEN]      [MSG CLOSED]
\               /
 \             /
  v           v
    [HANDOFF]
```

A condição deve consultar BusinessHoursService/configuração central.
Não duplicar cálculo de horário dentro de templates ou nodes diferentes.

============================================================
## 24. FALLBACK VISÍVEL NO GRAFO
============================================================

O fallback deve aparecer explicitamente.

Exemplo:

```text
[ IDENTIFICAR INTENÇÃO ]
       |
       ├── reparo
       ├── assistência
       ├── serviços
       ├── atendimento
       |
       └── UNKNOWN
              |
              v
        [ FALLBACK COUNT ]
          /    |     \
         0     1     >=2
         |     |      |
         v     v      v
       MSG1   MENU   HANDOFF
```

Assim fica fácil entender e ajustar o comportamento.

============================================================
## 25. ENABLE / DISABLE
============================================================

Permitir ativar/desativar elementos apenas quando existir comportamento seguro definido.

Exemplos:
- Conhecer serviços: ENABLED / DISABLED
- Consulta básica de status: ENABLED / DISABLED
- Coleta: ENABLED / DISABLED

Se um branch for desativado:
**NÃO** pode criar dead-end.
O editor deverá exigir um fallback.

Exemplo:
SERVICES disabled
→ opção desaparece do menu
ou:
→ redireciona para Atendimento
conforme configuração explícita.

============================================================
## 26. NÃO PERMITIR DESABILITAR CONTROLES DE SEGURANÇA
============================================================

O Flow Studio **NÃO** pode permitir desligar por graph:
- webhook authentication;
- dedupe;
- authorization;
- Human Lock global;
- tenant/security boundaries;
- sanitização;
- rate limit obrigatório;
- ownership checks;
- idempotência;
- secret handling.

Esses controles ficam fora do graph.
O graph define comportamento de negócio, não segurança fundamental.

============================================================
## 27. GLOBAL KILL SWITCH TEM PRECEDÊNCIA
============================================================

Mesmo se:
`flow.enabled=true`

se:
`BOT_AUTO_REPLIES_ENABLED=false`
nenhuma resposta automática é enviada.

Mesmo se:
`status flow enabled=true`

se:
`STATUS_NOTIFICATIONS_ENABLED=false`
nenhuma notificação é enviada.

Kill switches operacionais continuam soberanos.

============================================================
## 28. CONDITION REGISTRY
============================================================

Criar futuramente conceito equivalente a:
ConditionRegistry

Cada condição possui:
key
label
description
allowed_operators
result_type
required_context

Exemplo:
customer_match
Result:
NONE
UNIQUE
AMBIGUOUS

Outro:
business_hours
Result:
OPEN
CLOSED

Outro:
human_lock
Result:
ACTIVE
INACTIVE

O graph salva apenas:
condition_key
operator
expected_value
Não código.

============================================================
## 29. ACTION REGISTRY
============================================================

Criar conceito equivalente a:
ActionRegistry

Exemplo:
start_intake
cancel_intake
lookup_customer
lookup_os
request_location
activate_handoff
reset_fallback
return_menu

Cada action define:
input schema
output schema
side effects
permission/risk classification.

Não aceitar action inexistente.

============================================================
## 30. CONTEXT SCHEMA
============================================================

O flow não deve acessar objetos arbitrários.
Definir FlowContext com campos permitidos.

Exemplo conceitual:
- conversation
- session
- customer_match
- intake
- business_hours
- verification
- message
- feature_flags
- mapos_health
- logistics_state

Campos sensíveis devem ficar fora do contexto visual quando possível.

============================================================
## 31. CAMPOS AUSENTES DO CLIENTE
============================================================

O usuário mencionou um caso importante:
cliente possui cadastro, mas faltam informações úteis.

Prever condições como:
- customer_exists
- customer_missing_name
- customer_missing_phone
- customer_missing_city
- customer_missing_address

Porém:
o Bot **NÃO** deve perguntar automaticamente qualquer dado cadastral apenas porque está faltando.

Cada missing-field deve possuir regra de negócio explícita.

Exemplo:
PICKUP solicitado
+
address_missing
→ solicitar local da coleta.

Não:
cliente diz "oi"
→ Bot começa interrogatório cadastral.

============================================================
## 32. CLIENTE EXISTENTE / NÃO EXISTENTE
============================================================

Representar visualmente.

Exemplo:

```text
[ SOLICITAR ASSISTÊNCIA ]
          |
          v
[ LOOKUP CUSTOMER ]
      /    |      \
   NONE  UNIQUE  AMBIGUOUS
    |      |        |
    v      v        v
 ASK     START    SAFE
 NAME    INTAKE   HANDOFF
```

Não revelar ao cliente:
"existem dois cadastros"
em situação AMBIGUOUS.

O graph deve continuar usando resposta segura já definida pela arquitetura.

============================================================
## 33. FLOW DE STATUS SEPARADO
============================================================

As notificações transacionais **NÃO** devem ficar misturadas ao fluxo conversacional.

Criar flow separado:
STATUS NOTIFICATIONS

Exemplo conceitual:

```text
MAPOS EVENT
   |
   v
STATUS RULE EXISTS?
   |
   v
NOTIFICATIONS ENABLED?
   |
   v
HUMAN LOCK / LATEST-WINS
   |
   v
DETAIL LEVEL
   |
   v
TEMPLATE
   |
   v
QUEUE
   |
   v
EVOLUTION
```

Esse fluxo inicialmente poderá ser principalmente visual/read-only.

============================================================
## 34. NÃO TRANSFORMAR STATUS FLOW EM CHAT FLOW
============================================================

Diferença:

customer_service_main:
orientado a mensagens recebidas e sessão.

status_notifications:
orientado a eventos MapOS e fila transacional.

Manter semânticas separadas.

============================================================
## 35. SUBFLOWS
============================================================

Permitir encapsular partes.

Exemplo:
MAIN
├── SERVICES
├── STATUS_QUERY
├── INTAKE
├── HANDOFF
└── LOCATION

No canvas:
[ START INTAKE ]
pode representar SUBFLOW.

Clique duplo:
abre gráfico do Intake.

Isso evita centenas de nodes em uma tela.

============================================================
## 36. UI DO FLOW STUDIO
============================================================

Adicionar no painel:
Configurações
→ WhatsApp
→ Fluxos

Tela inicial:

FLUXOS

Atendimento automático
Ativo
Published v12
Draft v13

Pré-atendimento
Ativo
Published v7

Notificações de status
Ativo
Published v5

Logística
Planejado / futuro

Ações:
[ Abrir ]
[ Testar ]
[ Histórico ]
[ Duplicar ]
[ Exportar ]

============================================================
## 37. CANVAS
============================================================

Ao abrir flow:
canvas central com:
- zoom;
- pan;
- fit-to-screen;
- minimap quando útil;
- busca por node;
- agrupamento;
- cores por node type;
- indicação de enabled/disabled;
- indicação de erro;
- indicação de unpublished change.

Painel lateral:
NODE INSPECTOR.

Cabeçalho:
Flow
Version
Draft/Published
Validation status.

============================================================
## 38. NÃO EXIGIR REACT OU SPA SEM NECESSIDADE
============================================================

A implementação futura deve avaliar biblioteca de graph compatível com a arquitetura atual do MapOS.
Pode utilizar biblioteca madura de diagramas/graphs.

Porém:
não transformar o MapOS inteiro em React apenas por causa dessa funcionalidade.

Preferir integração isolada.
Se biblioteca exigir bundle específico:
manter assets da TecNina isolados.

Documentar impacto upstream.

============================================================
## 39. DRAG-AND-DROP
============================================================

Mover posição visual de nodes é seguro.
Criar/remover/conectar nodes afeta comportamento e requer mais controles.

Portanto implementar progressivamente.

Fase inicial:
- mover nodes;
- organizar canvas;
- editar mensagens;
- editar settings permitidos;
- visualizar condições;
- testar.

Depois:
- criar node;
- excluir node;
- conectar edge;
- alterar condition.

============================================================
## 40. POSIÇÃO VISUAL NÃO DEVE ALTERAR SEMÂNTICA
============================================================

Mover um node no canvas:
x=200
y=450

não deve mudar o comportamento.

Separar:
graph semantics
de:
graph layout.

============================================================
## 41. VALIDATION ENGINE
============================================================

Antes de publicar, validar obrigatoriamente:
- exatamente um START;
- edges inválidos;
- node inexistente;
- template inexistente;
- condition inexistente;
- action inexistente;
- placeholder inválido;
- caminho sem destino;
- node inalcançável;
- missing fallback;
- zero-wait cycle;
- subflow inexistente;
- versão incompatível;
- feature obrigatória ausente.

Erro:
PUBLICATION BLOCKED.

============================================================
## 42. WARNINGS
============================================================

Algumas situações não precisam impedir publicação.

Exemplo:
- node inalcançável proposital;
- branch desabilitado;
- mensagem muito longa;
- flow sem descrição;
- versão antiga de template.

Exibir warning separado de error.

============================================================
## 43. DRY RUN
============================================================

Antes de publicar:
[ VALIDAR ]
[ SIMULAR ]
[ PUBLICAR ]

Simulação não executa side effect real.
Actions devem possuir modo:
DRY_RUN.

Exemplo:
lookup customer
→ usa cenário fake.

send message
→ mostra texto, não envia.

============================================================
## 44. PUBLICAÇÃO EXPLÍCITA
============================================================

Nunca autosave de edição deve significar publicação.
Autosave:
DRAFT.

Somente botão:
PUBLICAR VERSÃO
muda runtime.

Solicitar confirmação:
"Publicar v13?
Novas conversas passarão a utilizar esta versão."

============================================================
## 45. DIFF
============================================================

Antes de publicar, mostrar resumo:
v12 → v13

Alterações:
- mensagem Welcome alterada;
- fallback máximo 2 → 3;
- branch Services desabilitado;
- condição Business Hours adicionada.

Não precisa inicialmente ser diff gráfico sofisticado.
Um diff semântico é suficiente.

============================================================
## 46. AUDIT LOG
============================================================

Registrar:
flow created
draft edited
template edited
validation performed
flow published
rollback
flow enabled
flow disabled
session migrated
test mode enabled

Com:
timestamp
operator
flow
version

Não registrar secrets.

============================================================
## 47. SESSION ACTIONS
============================================================

A partir de Conversas / Flow Inspector permitir futuramente:
[ Pausar Bot ]
[ Retomar Bot ]
[ Voltar ao menu ]
[ Reiniciar fluxo ]
[ Migrar para versão atual ]

Essas ações são diferentes.

PAUSAR BOT:
usa Human Lock/manual pause.

VOLTAR AO MENU:
altera somente estado conversacional apropriado.

REINICIAR FLUXO:
não pode apagar automaticamente:
- cliente;
- OS;
- intake aprovado;
- dados MapOS.

Deve haver confirmação.

============================================================
## 48. NÃO EDITAR BANCO DIRETAMENTE PELA UI
============================================================

Todas as ações:
MapOS browser
→ MapOS controller
→ Tecnina_bot_gateway client
→ Gateway Admin API
→ serviços internos.

Nunca:
JavaScript
→ banco tecnina_bot.

Nunca:
MapOS
→ SQL direto no tecnina_bot.

============================================================
## 49. SEGURANÇA DO EDITOR
============================================================

Continuar protegido por:
cSistema
ou futura permissão mais específica caso justificada.

Manter:
- CSRF;
- Bearer server-to-server;
- validação;
- sanitização;
- audit log.

Não devolver ao navegador:
- Evolution API key;
- MAPOS_BOT_TOKEN;
- webhook token;
- database URL;
- JID completo desnecessário;
- secrets.

============================================================
## 50. TEMPLATE SECURITY
============================================================

Editor de MESSAGE não aceita:
HTML arbitrário
JavaScript
template expressions arbitrárias.

Somente:
texto
+
placeholders whitelistados.

Exemplo:
{{business_hours}}
é válido.

{{__import__('os')}}...
obviamente inválido.

============================================================
## 51. IMPORT / EXPORT
============================================================

Pensando na futura transformação do Bot em produto:
prever formato exportável de flow.

Exemplo:
JSON versionado.

Deve conter:
schema_version
flow_key
nodes
edges
settings
template references

Não conter:
- secrets;
- tokens;
- IDs específicos desnecessários;
- dados de clientes;
- credenciais.

Import:
sempre como DRAFT.
Nunca publicar automaticamente um arquivo importado.

============================================================
## 52. FLOW SCHEMA VERSION
============================================================

Adicionar:
flow_schema_version

Isso permitirá atualizar o engine futuramente.
Exemplo:
schema_version = 1

Se versão futura não for suportada:
não executar silenciosamente.

============================================================
## 53. PRODUCTIZATION
============================================================

Pensando no Bot como possível produto futuro:
o engine não deve hardcodar:
TecNina
Antonina
horários
serviços
domínios
status específicos
nome do operador.

Esses valores devem vir de:
settings
templates
adapters
flow definitions.

TecNina será uma configuração/default instalada sobre um engine genérico.
Não generalizar prematuramente tudo, mas evitar dependências desnecessárias do nome TecNina no engine.

============================================================
## 54. ÚLTIMA VERSÃO CONHECIDA BOA
============================================================

Se um Draft estiver inválido:
runtime continua usando Published.

Se versão nova falhar na publicação:
runtime continua na versão anterior.

Nunca deixar Bot sem flow por falha administrativa.

============================================================
## 55. FLOW DESABILITADO
============================================================

Definir semântica segura.

customer_service_main disabled:
→ nenhuma resposta automática conversacional.

status_notifications disabled:
→ nenhuma nova notificação transacional é gerada/enviada conforme regra.

intake disabled:
→ opção de Intake deve desaparecer ou encaminhar para Atendimento.

Nunca deixar opção visível que conduz a dead-end.

============================================================
## 56. FALLBACK DE ENGINE
============================================================

Se runtime não conseguir carregar FlowDefinition:
não improvisar.
Registrar erro.
Fail closed.

Dependendo do flow:

Conversation:
→ Bot automático silencioso / atendimento disponível.

Status:
→ não enviar mensagem.

Nunca enviar template ou caminho parcialmente carregado.

============================================================
## 57. PERFORMANCE
============================================================

Não consultar banco e reconstruir todo graph a cada node.

Publicado pode ser:
- carregado/cacheado;
- invalidado após nova publicação.

O runtime deve usar versão consistente durante uma execução.

============================================================
## 58. CONCORRÊNCIA DE PUBLICAÇÃO
============================================================

Dois administradores editando:
usar optimistic concurrency/version.

Não permitir:
operador A publica v14
operador B, com tela velha, sobrescreve silenciosamente.

Retornar conflict e pedir refresh/review.

============================================================
## 59. TESTES DO GRAPH ENGINE
============================================================

Adicionar futuramente testes para:
- valid graph;
- missing START;
- two START nodes;
- dangling edge;
- invalid condition;
- invalid action;
- invalid template;
- missing fallback;
- zero-wait cycle;
- unreachable node;
- disabled branch;
- subflow missing;
- invalid schema_version;
- draft does not affect published;
- atomic publication;
- rollback;
- conversation pinned to version;
- new conversation uses latest;
- Human Lock overrides graph;
- kill switch overrides graph.

============================================================
## 60. TESTES DO SIMULADOR
============================================================

Testar:
- novo cliente;
- cliente existente;
- ambiguous;
- MapOS unavailable;
- fallback;
- business hours;
- intake;
- handoff;
- cancellation;
- menu;
- Human Lock;
- error path.

Dry-run nunca envia mensagem real.

============================================================
## 61. TESTES DE SESSÃO
============================================================

Testar:
- current node;
- restart flow;
- return menu;
- manual pause;
- resume;
- migrate version;
- conversation on old version after publication;
- expired session;
- active Intake;
- Human Lock.

============================================================
## 62. TESTES DE SEGURANÇA
============================================================

Testar:
- node com action não registrada;
- condition malformada;
- template injection;
- graph import com secret;
- graph import com URL arbitrária;
- graph import com script;
- usuário sem cSistema;
- CSRF;
- token inválido;
- direct Gateway Admin API sem token;
- attempt to access sensitive context;
- malicious graph JSON.

============================================================
## 63. TELEMETRIA
============================================================

Métricas úteis sem PII:
flow_started_total
flow_completed_total
flow_fallback_total
flow_handoff_total
flow_error_total

flow_transition_total pode possuir labels de baixo risco:
flow_key
node_type
result

Evitar:
phone
jid
customer_id
os_id
como labels Prometheus.

============================================================
## 64. FLUXO VISUAL E TESTES REAIS
============================================================

Para números controlados, futuramente mostrar ao vivo:

CLIENTE:
"oi"

Trace:
welcome
↓
menu
↓
WAIT_INPUT

CLIENTE:
"meu notebook não liga"

Trace:
intent parser
→ ASSISTANCE
→ intake
→ device already inferred? [conforme regra real]
→ next node

Isso deve ser extremamente útil para ajustar o Bot.

============================================================
## 65. BUSINESS HOURS — COPY CONFIRMADA
============================================================

Atualizar a documentação de mensagens também com esta decisão:

Dentro do horário:
"Responderemos assim que possível."

Fora do horário:
"Agora estamos fora do horário de atendimento. Sua mensagem ficou registrada e será revisada assim que possível."

Evitar a expressão:
"Atendimento humano"

Preferir:
"Atendimento"

Não informar automaticamente o próximo período de funcionamento.
Caso o cliente pergunte pelo horário:
mostrar a tabela de horários configurada.
Não transformar horário em SLA.

============================================================
## 66. RELAÇÃO COM O NLU FUTURO
============================================================

O futuro NLU não deve modificar diretamente o graph.
NLU deverá produzir:
IntentResult

Exemplo:
intent=REQUEST_SERVICE
confidence=0.91

O graph decide:
confidence suficiente?
→ branch REQUEST_SERVICE

confidence intermediária?
→ CONFIRM INTENT

baixa?
→ FALLBACK

Assim:
NLU é um componente de classificação.
FLOW continua controlando comportamento.

Isso permite substituir o NLU sem redesenhar o Bot.

============================================================
## 67. RELAÇÃO COM MAPOS
============================================================

Flow Condition:
customer_match
não executa SQL.

Ela chama abstração existente:
MapOSAdapter.

Flow Action:
query_status
também utiliza MapOSAdapter.

Preservar a regra:
Gateway nunca consulta tabelas de negócio MapOS diretamente.

============================================================
## 68. RELAÇÃO COM EVOLUTION
============================================================

Flow MESSAGE:
não chama URL Evolution diretamente.

Usa:
WhatsAppProvider / EvolutionAdapter.

Assim provider futuro pode substituir Evolution.

============================================================
## 69. RELAÇÃO COM LOGÍSTICA
============================================================

Quando logística for implementada:
flow principal
→ SUBFLOW logistics_pickup

O Flow Studio poderá visualizar:
zona?
equipamento permitido?
localização?
rota?
capacidade?
confirmar?

Mas LogisticsService continua sendo responsável pela regra complexa.
Não implementar algoritmo logístico inteiro dentro de condition nodes.

============================================================
## 70. REGRA DE COMPLEXIDADE
============================================================

O Flow Studio é um ORQUESTRADOR.

Não deve duplicar dentro do graph:
- regras de MapOS;
- algoritmo de logística;
- autenticação;
- dedupe;
- Human Lock;
- fila;
- geocoding;
- providers.

Os nodes chamam serviços existentes.

============================================================
## 71. PLANEJAMENTO DE IMPLEMENTAÇÃO
============================================================

Não implementar tudo de uma vez.

Sugestão:

ETAPA A — FLOW OBSERVER
- modelo de FlowDefinition;
- representação read-only da FSM atual;
- canvas;
- nodes/edges;
- current node;
- trace;
- simulator básico.

ETAPA B — VERSIONAMENTO
- draft;
- published;
- validation;
- publish;
- rollback;
- audit.

ETAPA C — MESSAGE EDITING
- editar template pelo node;
- preview;
- validation.

ETAPA D — CONFIG EDITING
- enable/disable;
- parâmetros;
- fallback settings;
- business hours branches.

ETAPA E — STRUCTURAL EDITING
- adicionar node;
- conectar;
- excluir;
- condition editor;
- action editor.

ETAPA F — TEST MODE
- draft por número allowlisted;
- live trace;
- migration de sessão.

Cada etapa deve possuir testes antes da seguinte.

============================================================
## 72. POSIÇÃO NA FEATURE REQUEST
============================================================

Adicionar como capacidade transversal posterior à base atual de Intake / Aprovação e anterior ao NLU avançado.

Se a numeração atual já possuir:
Fase 8.1 — Logística, Localização e Agendamento

utilizar preferencialmente:
FASE 8.2 — FLOW STUDIO / ORQUESTRAÇÃO VISUAL DO BOT
sem renumerar todo o documento.

Porém, se a versão atual da Feature Request já possuir outra numeração, não criar conflito apenas para manter 8.2.
Escolher a próxima subfase coerente e documentar.

============================================================
## 73. CRITÉRIOS DE ACEITE DO FLOW STUDIO
============================================================

A capacidade será considerada madura quando:

1. fluxos principais puderem ser visualizados;
2. atendimento e notificações forem separados;
3. subflows forem suportados;
4. node atual de uma conversa puder ser visualizado;
5. trace explicar transições;
6. simulator funcionar sem WhatsApp real;
7. cenários fake existirem;
8. messages puderem ser editadas com segurança;
9. templates continuarem versionados;
10. drafts não afetarem produção;
11. publicação for explícita;
12. rollback existir;
13. validação impedir graph inválido;
14. zero-wait loops forem bloqueados;
15. Human Lock continuar soberano;
16. kill switches continuarem soberanos;
17. sessões permanecerem em versão coerente;
18. edição concorrente for protegida;
19. audit log existir;
20. import/export não carregar secrets;
21. condição/action arbitrária não for permitida;
22. painel não acessar banco Gateway diretamente;
23. nenhum token chegar ao navegador;
24. testes de segurança passarem;
25. MapOS upstream continuar pouco acoplado;
26. Flow Studio puder evoluir futuramente para uso em outras instalações.

============================================================
## 74. RESTRIÇÃO DESTA SOLICITAÇÃO
============================================================

NESTA TAREFA:

NÃO implementar:
- graph engine;
- database migration;
- frontend;
- biblioteca visual;
- canvas;
- simulator;
- flow runtime;
- session migration;
- import/export;
- novos endpoints.

Somente atualizar:
FEATURE_REQUEST_TECNINA_WHATSAPP_MAPOS.md

Integrar esta capacidade de maneira coerente com:
- arquitetura existente;
- Fases 1–8;
- Human Lock;
- Intake;
- painel;
- templates;
- status;
- futura logística;
- futuro NLU.

Evitar duplicar regras já existentes.

============================================================
## 75. ENTREGA
============================================================

Ao concluir a atualização documental:

1. informar quais seções existentes foram ajustadas;
2. informar onde o Flow Studio foi inserido;
3. resumir a arquitetura proposta;
4. informar conflitos encontrados;
5. informar se algum conceito atual precisou ser generalizado;
6. confirmar que nenhum código funcional foi alterado;
7. confirmar que nenhuma migration foi criada;
8. confirmar que nenhuma fase foi iniciada;
9. parar e aguardar autorização.

---

### NOTAS ADICIONAIS SOBRE A PROPOSTA

A parte que considero mais importante dessa proposta é que o diagrama não vira o lugar onde mora toda a inteligência. Ele vira o orquestrador visível.

Então, em vez de colocar dentro de um quadradinho uma lógica absurda do tipo:

if cliente && status && horário && endereço...

o nó simplesmente pergunta ao serviço apropriado:

```text
[ Cliente cadastrado? ]
         ↓
   CustomerMatchService
```

ou:

```text
[ Horário de atendimento? ]
         ↓
   BusinessHoursService
```

ou:

```text
[ Human Lock? ]
         ↓
   HumanTakeoverService
```

Isso é o que faz essa ideia continuar administrável mesmo quando o sistema crescer.

E tem uma funcionalidade que eu considero especialmente valiosa para você: abrir uma conversa no painel e ver o próprio fluxograma com o quadrado onde aquele cliente está naquele momento destacado. Algo como:

```text
Menu
 ↓
Assistência
 ↓
Intake
 ↓
Equipamento
 ↓
Marca
 ↓
>>> MODELO <<<
 ↓
Problema
 ↓
Coleta
```

Somado ao trace de “por que ele veio parar aqui”, isso deve tornar manutenção e teste do Bot dramaticamente mais simples — e já cria uma fundação muito boa caso você realmente transforme o sistema em produto no futuro.
