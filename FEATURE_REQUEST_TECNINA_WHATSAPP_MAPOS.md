# Feature Request — Integração WhatsApp ↔ MapOS para a TecNina

## 1. Resumo

Desenvolver uma integração modular, segura, testável e atualizável entre o **MapOS** e o **WhatsApp**, usando uma camada intermediária independente chamada, neste documento, de **TecNina Bot Gateway**.

A integração deve permitir:

- notificações automáticas de eventos de Ordens de Serviço;
- respostas automáticas determinísticas básicas;
- consulta segura de informações do cliente quando aplicável;
- redirecionamento para a Área do Cliente para informações sensíveis ou detalhadas;
- detecção automática de intervenção humana;
- pausa temporária ou permanente da automação por conversa/cliente;
- pré-atendimento de novos clientes;
- coleta estruturada de dados antes do cadastro no MapOS;
- aprovação humana antes de criar clientes ou OS a partir do pré-atendimento;
- futura logística configurável de coleta e devolução, com agenda, capacidade e
  confirmação de localização, sem transformar movimentos logísticos em status
  da OS;
- painel administrativo dentro do MapOS para acompanhar e controlar a integração;
- futura adição de NLU estatístico sem LLM, sem refazer a arquitetura;
- futura substituição do provedor de WhatsApp ou até do MapOS sem reescrever toda a aplicação.

A prioridade inicial é **não construir um chatbot sofisticado**, e sim garantir que as automações transacionais de OS funcionem de forma confiável.

---

# 2. Objetivo principal

O primeiro objetivo operacional é:

> Ao criar ou alterar uma OS no MapOS, o sistema deve conseguir disparar automaticamente uma notificação via WhatsApp para o cliente, sem que o operador precise copiar informações manualmente, gerar PDF ou enviar mensagens individualmente.

Exemplo:

```text
MapOS
  ↓
status da OS mudou
  ↓
evento gerado
  ↓
TecNina Bot Gateway
  ↓
verifica regras / Human Lock / cliente
  ↓
Evolution API
  ↓
WhatsApp do cliente
```

Mensagem esperada:

```text
🔧 TecNina Assistência Técnica

Houve uma atualização em um reparo vinculado a este número.

Status: Aguardando peça

Para consultar detalhes, orçamento e demais informações com segurança, acesse:

https://cliente.tecnina.com
```

O WhatsApp deve funcionar principalmente como **canal de notificação e entrada de atendimento**.

Informações detalhadas ou de maior sensibilidade devem permanecer preferencialmente na Área do Cliente do MapOS.

---

# 3. Prioridades

## P0 — Arquitetura e segurança de atualização do MapOS

Antes de qualquer automação:

- garantir separação entre MapOS e Bot Gateway;
- minimizar alterações em arquivos upstream;
- definir contratos de integração;
- criar testes de compatibilidade;
- documentar customizações.

## P1 — Notificações transacionais da OS

Primeira funcionalidade realmente útil:

- criação de OS;
- mudança de status;
- envio de mensagem;
- retry;
- fila;
- logs;
- painel;
- Human Lock.

## P2 — Human Takeover

Garantir que o bot nunca interrompa atendimento humano.

## P3 — Painel de integração no MapOS

Permitir monitoramento e controle sem acessar diretamente o banco ou painel do Bot Gateway.

## P4 — Pré-atendimento de novos clientes

Bot coleta informações, cria um draft e aguarda aprovação humana.

## P4.1 — Logística, localização e agendamento

Depois que Intake e aprovação estiverem estáveis, permitir solicitar e
administrar `PICKUP` e `DELIVERY` no domínio do Gateway, com zonas, rotas,
capacidade, confirmação humana e localização exata consentida.

Essa prioridade não pode atrasar o primeiro MVP útil de notificações de OS.

## P5 — Autoatendimento determinístico

Menus, regras, FSM e consultas simples.

## P6 — Autenticação de consultas

Permitir consulta segura de status de OS sem depender apenas do número de telefone.

## P7 — NLU estatístico

Adicionar interpretação de linguagem natural sem LLM.

---

# 4. Princípio arquitetural central

## O MapOS não é o bot

O MapOS deve continuar sendo:

- fonte oficial de clientes;
- fonte oficial de OS;
- fonte oficial de serviços;
- fonte oficial de preços;
- fonte oficial dos dados administrativos.

O MapOS **não deve** concentrar:

- integração com Evolution;
- estados de conversação;
- Human Takeover;
- fila de WhatsApp;
- retry;
- NLU;
- templates de conversa;
- deduplicação de webhook;
- lógica de sessão;
- processamento de mensagens;
- fluxo de pré-atendimento;
- agenda, zonas, rotas ou capacidade logística;
- tokens e coordenadas de coleta/entrega.

Tudo isso deve permanecer no **TecNina Bot Gateway**.

---

# 5. Arquitetura final desejada

```text
                          ┌────────────────────────┐
                          │       WhatsApp         │
                          └───────────┬────────────┘
                                      │
                                      ▼
                          ┌────────────────────────┐
                          │     Evolution API      │
                          │   transporte WhatsApp  │
                          └───────────┬────────────┘
                                      │ webhook
                                      ▼
┌──────────────────────────────────────────────────────────────┐
│                    TECNINA BOT GATEWAY                       │
│                                                              │
│ Python + FastAPI + Uvicorn                                   │
│                                                              │
│ ├── EvolutionAdapter                                         │
│ ├── Canonical Message DTO                                    │
│ ├── deduplicação                                             │
│ ├── normalização de telefone                                 │
│ ├── Human Lock / Human Takeover                              │
│ ├── FSM                                                      │
│ ├── autenticação e autorização                               │
│ ├── templates                                                │
│ ├── filas / retries                                          │
│ ├── audit/log                                                │
│ ├── Intake / drafts                                          │
│ ├── Logistics / appointments / capacity                      │
│ ├── Location Requests                                        │
│ ├── MapOSAdapter                                             │
│ ├── Admin API                                                │
│ └── IntentEngine                                             │
│       ├── DeterministicIntentEngine                           │
│       └── SklearnIntentEngine (futuro)                        │
└────────────────┬────────────────────────┬────────────────────┘
                 │                        │
                 │ REST                   │ MySQL separado
                 ▼                        ▼
         ┌───────────────┐        ┌─────────────────┐
         │     MapOS     │        │   tecnina_bot   │
         │ REST/JSON API │        │ schema próprio  │
         └───────┬───────┘        └─────────────────┘
                 │
                 ▼
       ┌─────────────────────────┐
       │ Área do Cliente MapOS   │
       │ cliente.tecnina.com     │
       └─────────────────────────┘

Interface pública futura e mínima, ligada ao domínio do Gateway:

localizacao.tecnina.com/l/<opaque_token>
→ captura/confirmação consentida do ponto logístico
```

A interface pública de localização é apenas um adaptador de entrada. O
Gateway continua sendo a fonte de verdade e pode servi-la no mesmo projeto ou
em um deploy separado, conforme a decisão técnica da Fase 8.1. Ela nunca deve
acessar o banco do MapOS nem concentrar regras de agenda.

---

# 6. Compatibilidade com atualizações futuras do MapOS

Este requisito é crítico.

O MapOS é um fork que precisa continuar recebendo atualizações do repositório upstream.

## Regras obrigatórias

1. Tratar o MapOS upstream como software externo.
2. Minimizar alterações em arquivos existentes.
3. Preferir arquivos novos.
4. Preferir controllers próprios.
5. Preferir libraries próprias.
6. Preferir views próprias.
7. Preferir hooks oficiais.
8. Preferir endpoints próprios.
9. Nunca inserir lógica significativa do WhatsApp em controllers existentes.
10. Toda comunicação com o Bot deve ocorrer por contrato HTTP/evento documentado.
11. Manter inventário das customizações.
12. Criar testes de contrato para detectar incompatibilidades após merge upstream.
13. Objetivo: manter idealmente **no máximo 3 arquivos upstream modificados diretamente pela integração**.
14. Antes de modificar arquivo upstream, justificar por que não é possível fazer de forma aditiva.

## Estrutura sugerida no MapOS

```text
application/
├── controllers/
│   └── TecninaIntegration.php
│
├── libraries/
│   └── tecnina/
│       ├── BotClient.php
│       ├── EventPublisher.php
│       └── IntegrationConfig.php
│
├── models/
│   └── TecninaIntegration_model.php
│
├── views/
│   └── tecnina_integration/
│       ├── index.php
│       ├── conversations.php
│       ├── status_rules.php
│       ├── templates.php
│       ├── intakes.php
│       └── logs.php
│
└── config/
    └── tecnina_integration.php
```

Arquivos exclusivos da TecNina tendem a sobreviver a atualizações upstream sem conflito.

---

# 7. Estratégia de extensão do MapOS

## Regra de negócio

O MapOS deve conhecer o mínimo possível da integração.

Idealmente ele precisa saber apenas:

```text
BOT_GATEWAY_URL
BOT_GATEWAY_TOKEN
INTEGRATION_ENABLED
```

O Bot Gateway conhece:

```text
Evolution URL
Evolution Token
Evolution instance
MapOS URL
MapOS credentials
timeouts
retries
templates
Human Lock
status rules
NLU
etc.
```

---

# 8. MapOSAdapter

Toda comunicação Bot → MapOS deve passar por:

```text
MapOSAdapter
```

Nunca:

```text
Bot
  ↓
consulta SQL direta no schema do MapOS
```

Sempre:

```text
Bot
  ↓
MapOSAdapter
  ↓
HTTP API
  ↓
MapOS
```

Se hoje:

```text
GET /api/v1/os/{id}
```

e no futuro virar:

```text
GET /api/v2/orders/{id}
```

somente o `MapOSAdapter` deve precisar mudar.

---

# 9. Operações não existentes na API

Quando a API oficial não expuser uma operação necessária:

```text
/api/bot/*
```

pode ser criado no MapOS.

Exemplos:

```text
GET  /api/bot/client-by-phone
POST /api/bot/intakes/{id}/approve
GET  /api/bot/integration-context/{os_id}
```

Regras:

- payload mínimo;
- autenticação interna;
- nenhum dado desnecessário;
- não reutilizar busca genérica com `LIKE` para autenticação.

---

# 10. Banco do Bot Gateway

Usar, preferencialmente, o mesmo servidor MySQL já existente, porém com:

```text
mysql
├── mapos
└── tecnina_bot
```

Usuários separados:

```text
mapos_user
tecnina_bot_user
```

O `tecnina_bot_user` não deve possuir acesso irrestrito às tabelas do MapOS.

## Tabelas iniciais sugeridas

```text
settings
conversation_sessions
client_overrides
processed_webhooks
message_log
notification_queue
verification_sessions
message_templates
status_rules
audit_log
intake_drafts
bot_generated_messages
logistics_zones
logistics_routes
logistics_route_zones
logistics_blackouts
logistics_appointments
logistics_capacity_rules
equipment_logistics_profiles
location_requests
```

Como evolução compatível com múltiplos equipamentos e múltiplas OS por visita,
prever futuramente `logistics_appointment_items`, sem impor relação 1:1 entre
appointment, equipamento e OS.

---

# 11. Evolution API

Evolution API será inicialmente o provedor de transporte.

Toda comunicação deve passar por abstração:

```text
WhatsAppProvider
   └── EvolutionProvider
```

Preparar arquitetura para:

```text
WhatsAppProvider
├── EvolutionProvider
├── WPPConnectProvider
└── MetaCloudProvider
```

Não espalhar chamadas HTTP da Evolution pelo código.

---

# 12. Canonical Message DTO

Todo payload do provedor deve ser normalizado imediatamente.

Exemplo:

```json
{
  "provider": "evolution",
  "instance_id": "tecnina-01",
  "remote_jid": "5541XXXXXXXX",
  "conversation_id": "5541XXXXXXXX",
  "message_id": "3EB0...",
  "text": "queria saber da OS 4821",
  "from_me": false,
  "timestamp": 1788534000,
  "raw_event_type": "messages.upsert"
}
```

## Chave de sessão

Nunca usar somente telefone.

Usar:

```text
(provider, instance_id, remote_jid)
```

---

# 13. Deduplicação de webhook

Criar índice/constraint UNIQUE:

```text
provider
instance_id
message_id
```

Se webhook já foi processado:

- retornar sucesso;
- não executar regras novamente;
- não duplicar logs;
- não reenviar mensagem;
- não alterar sessão duas vezes.

---

# 14. Tratamento de grupos

Por padrão:

- ignorar mensagens de grupo;
- não iniciar fluxo automático em grupo;
- não consultar dados privados;
- não criar intake;
- registrar apenas se necessário para diagnóstico.

Essa regra deve ser configurável futuramente, mas deve começar desligada.

---

# 15. Human Takeover / Human Lock

## Estados

```text
AUTO
HUMAN_TEMPORARY
HUMAN_MANUAL
```

## Funcionamento

Ao receber:

```text
from_me = true
```

verificar se o `message_id` foi criado pelo próprio Bot Gateway.

### Mensagem pertence ao Bot

```text
BOT enviou
→ from_me=true
→ ID está em bot_generated_messages
→ não ativar Human Lock
```

### Mensagem não pertence ao Bot

```text
from_me=true
ID desconhecido
→ assumir intervenção humana
→ HUMAN_TEMPORARY
```

## Tempo padrão

```text
30 minutos
```

Configurável.

Cada nova mensagem humana:

```text
human_until = now + timeout
```

## Enquanto Human Lock está ativo

Não:

- enviar menu;
- responder automaticamente;
- executar NLU;
- iniciar novo fluxo;
- pedir dados;
- interromper atendimento.

Mensagens do cliente continuam sendo registradas.

---

# 16. HUMAN_MANUAL

Deve existir pausa indefinida.

Exemplo:

```text
Cliente
→ automação pausada manualmente
→ só volta quando operador clicar em retomar
```

---

# 17. Diferença entre resposta automática e notificação transacional

São dois canais lógicos diferentes.

## Resposta automática

Exemplo:

```text
Olá! Como posso ajudar?
```

Durante Human Lock:

```text
BLOQUEAR
```

## Notificação transacional

Exemplo:

```text
Sua OS mudou de status.
```

Durante Human Lock:

```text
NÃO descartar
→ manter pendente
→ consolidar ao final
```

Para logística, perguntas automáticas, oferta de janelas e solicitação de
localização são `AUTO_REPLY` e ficam bloqueadas. Uma confirmação disparada por
ação administrativa explícita pode gerar `TRANSACTIONAL_NOTIFICATION`, mas a
ação e a opção de enviar devem ser auditáveis e não podem reativar a conversa
automática.

---

# 18. Latest-wins / consolidação

Exemplo:

```text
10:05 Diagnóstico concluído
10:10 Orçamento
10:15 Aguardando aprovação
```

Se o contato estava em atendimento humano, após liberar automação:

não enviar três mensagens.

Enviar apenas:

```text
Seu reparo teve atualizações.

Status atual: Aguardando aprovação.

cliente.tecnina.com
```

A fila deve suportar agrupamento e consolidação por:

```text
cliente
OS
tipo de evento
```

Appointments logísticos usam a mesma semântica de consolidação por cliente,
appointment e tipo. Em vários reagendamentos, enviar apenas a janela atual.
`CANCELLED` tem precedência: uma confirmação antiga nunca pode ser enviada
depois do cancelamento.

---

# 19. Outbox de eventos do MapOS

Não fazer o update de uma OS depender da disponibilidade do WhatsApp.

## Fluxo

```text
BEGIN TRANSACTION

UPDATE OS

INSERT integration_outbox

COMMIT
```

Evento:

```json
{
  "event_id": "uuid",
  "event_type": "os.status_changed",
  "os_id": 142,
  "client_id": 37,
  "old_status": "Em andamento",
  "new_status": "Aguardando peças",
  "created_at": "..."
}
```

Não incluir dados pessoais desnecessários.

---

# 20. Avaliar trigger MySQL como alternativa

Durante a fase de discovery, avaliar:

```text
AFTER UPDATE ON os
```

para detectar:

```text
OLD.status != NEW.status
```

Vantagem:

- evita alterar controller;
- funciona independentemente da origem da alteração;
- reduz conflitos com upstream.

Não implementar automaticamente sem avaliar:

- nome real da tabela;
- coluna real;
- estabilidade do schema;
- migrations;
- possíveis efeitos colaterais.

Se trigger não for apropriado, usar uma única chamada de evento em ponto central.

---

# 21. Eventos iniciais

Começar com:

```text
os.status_changed
```

Depois:

```text
os.created
os.budget_ready
os.finished
```

Não criar dezenas de eventos antes de existir necessidade real.

Eventos logísticos futuros (`logistics.requested`, `logistics.confirmed`,
`logistics.rescheduled`, `logistics.cancelled`, `logistics.completed`) nascem
no Gateway e não pertencem à trigger/outbox da OS MapOS.

---

# 22. Regras de status

Não espalhar:

```python
if status == "Aguardando Peças":
```

pelo código.

Criar configuração central:

```text
status_rules
```

Campos:

```text
id
mapos_status
enabled
public_label
template_key
coalesce_group
priority
created_at
updated_at
```

Exemplo:

| Status MapOS | Notificar | Status público |
|---|---:|---|
| Aberto | sim | Recebido |
| Em análise | sim | Em diagnóstico |
| Aguardando peças | sim | Aguardando peça |
| Em andamento | opcional | Em reparo |
| Finalizado | sim | Serviço concluído |

## Status desconhecido

Regra segura:

```text
não enviar
```

Painel deve alertar:

```text
Status "Teste bancada" não possui regra de WhatsApp.
```

---

# 23. Templates

Templates devem ser editáveis e versionáveis.

Exemplo:

```text
🔧 TecNina Assistência Técnica

Houve uma atualização em um reparo vinculado a este número.

Status: {{public_status}}

Para consultar detalhes, orçamento e demais informações com segurança, acesse:
{{portal_url}}
```

## Placeholders permitidos

Usar whitelist.

Exemplos:

```text
{{public_status}}
{{os_id}}
{{portal_url}}
{{first_name}}
{{device_name}}
{{logistics_operation_label}}
{{logistics_date}}
{{logistics_window}}
{{location_or_map_url}}
```

Não permitir:

- execução arbitrária;
- código;
- acesso a propriedades não autorizadas;
- template injection.

Templates logísticos devem distinguir preferência registrada, confirmação,
reagendamento, cancelamento e conclusão. Nunca escrever “confirmado” enquanto
o appointment estiver em `PENDING_CONFIRMATION`. Links devem ser temporários e
mensagens não devem expor coordenadas, endereço completo ou rotina do operador.

---

# 24. Informações que não devem ser enviadas automaticamente sem autenticação

Evitar:

```text
CPF
RG
endereço completo
laudo detalhado
diagnóstico detalhado
anexos
fotografias
PDF
dados cadastrais
dados financeiros completos
```

Para isso usar:

```text
https://cliente.tecnina.com
```

---

# 25. Área do Cliente

A Área do Cliente deve ser o destino preferencial para:

- detalhes da OS;
- orçamento;
- anexos;
- laudos;
- informações privadas;
- ações sensíveis.

Não enviar token permanente de autenticação pelo WhatsApp.

---

# 26. Identificação x autenticação

## Identificação

O número do WhatsApp pode ajudar a localizar o cadastro.

## Autenticação

O número por si só não deve ser considerado prova absoluta.

Possíveis problemas:

- número reciclado;
- telefone compartilhado;
- telefone de familiar;
- mesmo número em múltiplos clientes;
- divergência com/sem nono dígito;
- mudança de linha.

---

# 27. Normalização de telefone brasileiro

Implementar módulo dedicado.

Aceitar:

```text
+55 41 91234-5678
5541912345678
41912345678
(41) 91234-5678
```

Representação interna canônica.

## Nono dígito legado

Tratar equivalência com/sem o `9` apenas quando:

- país for Brasil;
- DDD for compatível;
- diferença for exclusivamente o nono dígito;
- demais dígitos forem idênticos;
- regra for inequivocamente válida.

Nunca:

```text
comparar somente os últimos 8 dígitos
```

Nunca:

```text
LIKE %12345678%
```

como autenticação.

---

# 28. Telefone duplicado

Se um telefone corresponder a múltiplos clientes:

não responder:

```text
Encontrei Maria e João.
```

Isso revela informação.

Responder genericamente:

```text
Encontrei mais de um cadastro relacionado a este número.

Para continuar, informe o número/código do atendimento ou utilize a Área do Cliente.
```

---

# 29. Número reciclado

Número reconhecido não significa que o atual dono é o cliente histórico.

Para liberar dados:

- solicitar verificação adicional;
- não considerar vínculo permanente;
- permitir expiração da verificação.

---

# 30. Níveis de acesso

## Nível 0 — Público

Sem autenticação:

- horário;
- endereço;
- informações genéricas;
- tipos de serviço;
- formas de contato.

## Nível 1 — Cliente verificado

Pode acessar:

- status básico da própria OS;
- andamento básico.

## Nível 2 — Forte

Usar Portal do Cliente para:

- laudo;
- anexos;
- valores sensíveis;
- aprovação;
- alteração cadastral;
- dados pessoais.

---

# 31. Código de consulta da OS

Adicionar futuramente código aleatório por OS.

Exemplo:

```text
OS #1042
Código: 7K4P9M
```

Uso:

```text
telefone desconhecido
+
código da OS
→ acesso apenas àquela OS
```

Nunca:

```text
código de uma OS
→ acesso automático a todas as OS do cliente
```

---

# 32. Sessão verificada

Tabela:

```text
verification_sessions
```

Campos:

```text
client_id
remote_jid
verified_at
verification_method
expires_at
last_activity
```

Verificação pode ter validade configurável.

Para ações sensíveis, exigir Portal mesmo com sessão verificada.

---

# 33. Autoatendimento determinístico

Primeira versão sem NLU.

Mensagem:

```text
Olá! 👋 Sou o atendimento automático da TecNina.

Como posso ajudar?

1 — Acompanhar meu reparo
2 — Preciso de assistência/reparo
3 — Consultar serviços
4 — Falar com atendimento
```

---

# 34. FSM de conversa

Estados conceituais:

```text
IDLE
WAITING_MENU_SELECTION
WAITING_OS_ID
VERIFYING_CUSTOMER
QUERYING_MAPOS
RESPONDING

INTAKE_START
WAITING_NAME
WAITING_DEVICE
WAITING_PROBLEM
WAITING_SERVICE_MODE
INTAKE_REVIEW
READY_FOR_HUMAN

HUMAN_TEMPORARY
HUMAN_MANUAL
BOT_RECOVERY
```

A FSM deve ser determinística.

Estados de conversa, estados do Intake, estados logísticos e status da OS são
máquinas diferentes. `PENDING_CONFIRMATION` logístico, por exemplo, não deve
ser acrescentado à FSM conversacional nem ao cadastro de status do MapOS.

---

# 35. Debounce de mensagens

Cliente frequentemente envia:

```text
Oi

meu notebook

queria saber

se ficou pronto
```

Implementar pequeno debounce, por exemplo:

```text
3–5 segundos
```

Antes de processar.

Durante debounce:

- agrupar mensagens;
- se humano responder, cancelar resposta pendente;
- não gerar quatro fluxos independentes.

Valor deve ser configurável.

---

# 36. Pré-atendimento / Intake

Novo cliente:

```text
WhatsApp
→ Bot
→ coleta dados
→ draft
→ operador revisa
→ operador aprova
→ MapOS
```

O bot não deve criar cliente/OS automaticamente na fase inicial.

---

# 37. Dados do intake

Coletar somente o necessário.

Sugestão inicial:

```text
nome
telefone
tipo de equipamento
marca
modelo
descrição do problema
tipo de atendimento
cidade/região
observações
```

Opcional:

```text
operação logística desejada: PICKUP ou DELIVERY
cidade
bairro ou CEP
preferência de janela
localização exata e referência
```

Somente quando necessários ao movimento solicitado e de forma progressiva. Não
pedir endereço completo ou GPS a todo cliente antes de saber se a região, o
porte do equipamento e a operação são elegíveis.

Não exigir:

```text
CPF
RG
data de nascimento
```

apenas para pré-atendimento.

---

# 38. Intake Draft

Tabela:

```text
intake_drafts
```

Campos sugeridos:

```text
id
conversation_id
remote_jid

status

name
phone_canonical
device_type
brand
model
problem_description
service_type
service_mode
city
notes

possible_mapos_client_id

created_at
updated_at
ready_at

approved_at
approved_by

mapos_client_id
mapos_os_id
```

Um draft pode originar um appointment logístico antes da OS. O vínculo
posterior deve ocorrer pelo `intake_id`/`logistics_appointment_id` e pelos IDs
MapOS persistidos no Gateway, sem adicionar coluna logística ao MapOS por
padrão.

Estados:

```text
COLLECTING
INCOMPLETE
READY
UNDER_REVIEW
APPROVED
REJECTED
EXPIRED
```

---

# 39. Cliente existente abrindo novo atendimento

Se telefone localizar cliente existente:

não presumir que ele quer a OS anterior.

Oferecer:

```text
1 — Acompanhar um reparo
2 — Solicitar atendimento para outro equipamento
3 — Falar com atendimento
```

Novo intake:

```text
client_id = cliente já existente
```

Operador pode criar nova OS sem criar cliente duplicado.

---

# 40. Aprovação humana do intake

Aba:

```text
Configurações
→ WhatsApp
→ Pré-atendimentos
```

Permissão inicial: `cSistema`.

Exemplo:

```text
Pré-atendimento #37

WhatsApp:
(41) 99999-9999

Nome:
João da Silva

Equipamento:
Samsung Galaxy A54

Problema:
"caiu no chão e a tela ficou preta"

Forma:
Coleta

Cidade:
Antonina

Possível cliente existente:
Nenhum

[ Editar ]
[ Descartar ]
[ Aprovar e cadastrar ]
```

---

# 41. Aprovação idempotente

Regra obrigatória:

> Dois cliques, reload, retry HTTP ou reprocessamento nunca podem criar dois clientes ou duas OS.

Usar:

- chave idempotente;
- estado APPROVED;
- transaction;
- IDs MapOS persistidos.

---

# 42. Detecção de cliente duplicado antes da aprovação

Fluxo:

```text
Aprovar
  ↓
normalizePhone()
  ↓
MapOSAdapter.findClient()
```

## Nenhum

Criar cliente + OS.

## Um

Mostrar:

```text
Cliente existente encontrado.

[ Vincular ]
[ Criar novo mesmo assim ]
```

## Vários

Exigir revisão humana.

Nunca selecionar silenciosamente.

---

# 43. Mídia no intake

Futuro.

Possível:

```text
cliente envia fotos
→ draft armazena referência
→ operador aprova
→ anexos podem ser enviados à OS
```

Não implementar no MVP.

Antes é necessário definir:

- download;
- storage;
- tamanho;
- MIME;
- antivírus/opsec;
- retenção;
- upload MapOS.

---

# 44. Human Lock durante intake

Se operador responder manualmente no meio:

```text
INTAKE_PAUSED
+
HUMAN_TEMPORARY
```

Draft permanece salvo.

Bot não continua fazendo perguntas.

Operador pode:

- concluir manualmente;
- retomar depois;
- editar draft no painel.

Se já houver solicitação logística em andamento, o Human Lock também deve
interromper perguntas automáticas de zona, janela e localização. Ele não apaga
preferências já coletadas nem impede uma ação administrativa explícita do
operador.

---

# 44.1. Domínio logístico futuro — Fase 8.1

## Limites e fonte de verdade

Logística é um domínio próprio do TecNina Bot Gateway. Separar sempre:

```text
estado do Intake
estado do movimento logístico
status de reparo da OS MapOS
```

`Coleta agendada` e `Entrega agendada` não são status de reparo. Não criar
status customizado, tabela logística ou regra de capacidade no schema MapOS.
O Gateway será fonte de verdade para agenda, zonas, rotas, localização,
capacidade, preço logístico, confirmação, reagendamento, cancelamento e
conclusão.

A operação inicial terá uma única pessoa, por isso a confirmação humana é
obrigatória no MVP. Essa realidade não deve virar código pessoal: dias,
horários, regiões, capacidade e motivos de deslocamento são configuração
operacional, sem endereço pessoal, local de trabalho ou descrição da rotina.

Tipos iniciais do mesmo agregado:

```text
PICKUP   -> buscar equipamento no local confirmado
DELIVERY -> devolver/entregar equipamento no local confirmado
```

Não criar arquiteturas duplicadas de coleta e entrega. Uma OS pode possuir
zero ou muitos movimentos, e um appointment pode existir antes da OS. Também
não impor `1 appointment = 1 OS = 1 equipamento`.

## Modelo conceitual no banco `tecnina_bot`

Os nomes finais serão refinados ao criar as migrations Alembic da Fase 8.1:

| Entidade | Responsabilidade e campos conceituais principais |
|---|---|
| `logistics_zones` | Região ativa, nome, cidade, regras de bairro/CEP, elegibilidade, pricing mode, taxa opcional, notas e ordenação. |
| `logistics_routes` | Rota recorrente, timezone IANA, dias, janela local, operações permitidas, antecedência, cutoff, perfil de transporte e regras de capacidade. |
| `logistics_route_zones` | Relação muitos-para-muitos entre rotas e zonas. |
| `logistics_blackouts` | Exceção por data/rota/janela, indisponibilidade ou override explícito de horário/capacidade. |
| `logistics_capacity_rules` | Limites totais, por classe de equipamento, por janela e por dia. |
| `equipment_logistics_profiles` | Mapeamento configurável do tipo de equipamento para classe logística e compatibilidade de transporte. |
| `logistics_appointments` | Movimento real, operação, estado, vínculos opcionais, janelas pedida/confirmada, regra de preço, contexto dos itens, notas, auditoria e idempotência. |
| `location_requests` | Solicitação temporária de localização, token hash, origem, estado, coordenadas, precisão, número, complemento, referência, validade e confirmação. |
| `logistics_appointment_items` | Evolução futura para múltiplos equipamentos e múltiplas OS na mesma visita. |

Campos de vínculo de `logistics_appointments` devem aceitar `null` enquanto o
dado ainda não existir:

```text
intake_id
conversation_id
mapos_client_id
mapos_os_id
zone_id
route_id
location_request_id
```

Demais atributos conceituais do appointment:

```text
operation_type
status
requested_date
requested_window_start / requested_window_end
confirmed_window_start / confirmed_window_end
pricing_mode / quoted_fee nullable
equipment_context + schema_version
customer_notes / operator_notes
created_at / updated_at
confirmed_at / confirmed_by
completed_at / cancelled_at
idempotency_key UNIQUE
```

`mapos_os_id` pode servir como referência principal no MVP, sem constraint 1:1
que impeça nova coleta, nova tentativa de entrega, redelivery ou uma visita com
múltiplos itens. `equipment_context` deve ser um snapshot tipado/versionado;
quando a operação exigir itens individualizados, migrar para
`logistics_appointment_items`.

## Estados e transições

Estados iniciais persistidos:

```text
REQUESTED
PENDING_CONFIRMATION
CONFIRMED
COMPLETED
CANCELLED
RESCHEDULE_REQUIRED
```

`NOT_REQUESTED` deve ser preferencialmente um estado de apresentação derivado
da ausência de appointment, e não uma linha vazia criada para todo intake/OS.
Estados futuros como `IN_ROUTE`, `NO_SHOW` e `FAILED` só entram após necessidade
operacional comprovada. Na interface, `COMPLETED` pode aparecer como
`COLLECTED` para PICKUP e `DELIVERED` para DELIVERY sem duplicar a máquina de
estados prematuramente.

Fluxo inicial:

```text
REQUESTED -> PENDING_CONFIRMATION -> CONFIRMED -> COMPLETED
```

Transições devem ser explícitas, auditadas, idempotentes e impedir regressão
por evento atrasado. Alterar o local de um appointment confirmado exige nova
validação e deve levar a `RESCHEDULE_REQUIRED` quando zona, preço, rota ou
capacidade puderem mudar.

## Zonas, rotas, preços e equipamento

O MVP pode identificar zona por cidade, bairro, CEP, intervalo de CEP ou regra
administrativa explícita. Não implementar geofencing sofisticado inicialmente.
Elegibilidade e preço não podem ser reduzidos a um booleano de coleta grátis:

```text
FREE
FIXED_FEE
MANUAL_QUOTE
UNAVAILABLE
```

A decisão pode depender de:

```text
operation_type
+ zone
+ equipment_logistics_class
+ route
+ transport_profile
```

Classes conceituais iniciais, todas configuráveis:

```text
COMPACT
MEDIUM
BULKY
```

Não hardcodar celular/notebook/gabinete nem nomes como moto, bicicleta ou
carro. Um `transport_profile` descreve capabilities por classe; o motivo
pessoal da disponibilidade do operador nunca deve aparecer em código ou dados.
Equipamento `BULKY` pode exigir `MANUAL_QUOTE`, outra rota ou avaliação humana,
mesmo dentro de zona gratuita. Cobrança/pagamento não faz parte da Fase 8.1;
somente a decisão e o valor cotado opcional ficam previstos.

Rotas representam janelas recorrentes configuráveis e podem aceitar PICKUP,
DELIVERY ou ambos. Uma rota atende várias zonas e uma zona pode ter várias
rotas. Regras específicas/blackouts prevalecem sobre recorrência.

Usar timezone IANA configurável, inicialmente `America/Sao_Paulo`. Regras de
dia e janela são avaliadas nesse timezone; instantes confirmados e auditoria
devem ser persistidos em UTC e convertidos na apresentação. Nunca usar offset
UTC fixo.

## Disponibilidade e capacidade

Ao oferecer horários, considerar apenas opções futuras compatíveis com:

- rota e zona ativas;
- operação permitida;
- classe do equipamento e perfil de transporte;
- dia/janela local e timezone;
- blackout ou exceção mais específica;
- antecedência mínima e cutoff;
- capacidade da janela e capacidade diária;
- limite total de itens e quota por classe.

Oferecer preferencialmente duas ou três janelas úteis, nunca uma lista extensa.
`PENDING_CONFIRMATION` registra preferência e não precisa consumir capacidade
definitiva no MVP. Somente a confirmação administrativa consome capacidade
dura.

Na confirmação, revalidar capacidade dentro de transação e com controle de
concorrência apropriado ao MySQL, impedindo que duas confirmações excedam o
último limite disponível. Clique duplo/retry deve devolver o mesmo resultado.
Override futuro, se existir, deve ser explícito, autorizado e auditado.

Capacidade não é apenas número de paradas. Prever regras equivalentes a:

```text
max_total_items
max_by_equipment_class
daily_max_total_items
daily_max_by_equipment_class
```

Duas rotas no mesmo dia não duplicam automaticamente a capacidade física do
operador.

## Intake e confirmação humana

Quando houver solicitação de PICKUP ou DELIVERY:

```text
identificar equipamento e classe
-> coletar cidade e bairro/CEP progressivamente
-> resolver zona e elegibilidade
-> indicar FREE / MANUAL_QUOTE / UNAVAILABLE
-> oferecer poucas janelas
-> registrar preferência
-> solicitar localização exata quando necessária
-> coletar número/complemento/referência
-> PENDING_CONFIRMATION
-> operador revisa e confirma
```

Na primeira versão o Bot nunca promete confirmação apenas porque uma janela
parece disponível. A frase deve indicar que a preferência foi registrada e que
o atendimento ainda confirmará. `CONFIRMED` somente resulta de ação
administrativa explícita e de nova validação de capacidade.

Não criar DELIVERY automaticamente quando a OS for finalizada. Retirada pelo
cliente, pagamento pendente ou liberação operacional podem exigir outro fluxo;
a criação/liberação de DELIVERY depende de regra ou ação explícita.

## Localização exata e privacidade

O endereço cadastral do MapOS não é automaticamente o local logístico. PICKUP
e DELIVERY podem usar pontos diferentes e cada movimento deve apontar para uma
localização confirmada ou reutilizada conscientemente. Nunca atualizar o
cadastro MapOS a partir disso sem fluxo separado e autorização explícita.

Para locais ausentes, incompletos ou mal georreferenciados, latitude e
longitude confirmadas pelo cliente podem ser a principal referência
operacional. Coordenadas não provam residência, identidade ou autorização.

Prever interface pública mobile-first:

```text
https://localizacao.tecnina.com/l/<OPAQUE_TOKEN>
```

O hostname é apenas planejamento nesta etapa. Quando a fase for autorizada,
seguir o padrão de infraestrutura Coolify + Cloudflare Tunnel já adotado para
`wa-api.tecnina.com` e `bot-api.tecnina.com`, sem expor diretamente o serviço
interno.

O token deve ter alta entropia, expiração e revogação; armazenar somente
`token_hash`. Não incluir telefone, CPF, nome, endereço ou ID previsível na
URL. Para permitir recaptura quando a precisão estiver ruim, o token não deve
ser consumido no primeiro acesso: ele se torna single-use/encerrado após a
confirmação válida, com submissões repetidas tratadas idempotentemente.

A rota e o token não podem aparecer em logs comuns de proxy/aplicação. Aplicar
HTTPS, `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, CSP restritiva,
rate limit e ausência de trackers/recursos terceiros desnecessários. Definir
retenção e acesso administrativo para coordenadas exatas; nunca incluí-las em
correlation logs ou mensagens de erro.

A página usa Browser Geolocation API somente após clique explícito e registra:

```text
latitude
longitude
accuracy_meters
captured_at
source
```

Validar faixas de latitude/longitude e usar threshold de precisão configurável.
Baixa precisão não pode ser aceita silenciosamente. Mostrar pin/mapa, permitir
confirmar, tentar novamente e ajustar manualmente. Leaflet/OpenStreetMap é uma
opção futura, mas a Fase 8.1 deve avaliar licença, política e capacidade do
provedor de tiles; não depender automaticamente do servidor público de tiles
em produção.

GPS não substitui número, complemento e referência livre. Fontes conceituais:

```text
BROWSER_GPS
WHATSAPP_LOCATION
MANUAL_PIN
MANUAL_ADDRESS
```

Estados de `location_requests`:

```text
PENDING
CAPTURED
CONFIRMED
EXPIRED
CANCELLED
SUPERSEDED
```

Cada request também deve registrar `purpose` (`PICKUP` ou `DELIVERY`), `source`,
`conversation_id` e vínculos opcionais com intake, OS e appointment, além de
`created_at`, `expires_at`, `captured_at`, `confirmed_at` e
`superseded_by`. Número, complemento e referência pertencem ao request/local
confirmado, não ao cadastro geral do cliente.

Se GPS for negado, indisponível, impreciso ou aberto no desktop, oferecer nova
tentativa, WhatsApp Location, ajuste manual de pin ou endereço/referência
manual. O Bot pode enviar resumo sem expor coordenadas completas e pedir uma
confirmação final.

## Integração com MapOS e eventos

O MapOS poderá consultar a Admin API do Gateway e exibir links/estado mínimo,
mas nunca acessar SQL do `tecnina_bot`. Uma anotação administrativa opcional na
OS pode registrar janela e identificador público do appointment, usando um
campo nativo adequado e sem virar fonte de verdade ou ser parseada pelo Bot.

Eventos conceituais nascem no Gateway, não na trigger/outbox de OS:

```text
logistics.requested
logistics.confirmed
logistics.rescheduled
logistics.cancelled
logistics.completed
```

Fluxo:

```text
domínio logístico do Gateway
-> notification_queue
-> WhatsAppProvider
-> Evolution
```

Não implementar otimização automática de rota, trânsito em tempo real, tracking
GPS, Google Directions, Mapbox Routing ou OSRM no MVP. Pode existir apenas
`manual_sequence` para ordenação visual e uma futura interface
`RoutingProvider`, sem contaminar o domínio atual.

---

# 45. Painel administrativo dentro do MapOS

Criar página central:

```text
Configurações
→ WhatsApp
```

Evitar espalhar controles em muitas páginas.

Usar inicialmente a permissão existente `cSistema`, conforme confirmado na
Fase 0. Uma categoria `Integrações` só deverá ser criada futuramente se outras
integrações justificarem a mudança.

---

# 46. Aba — Visão Geral

Exibir:

```text
Integração              ● Ativa
Bot Gateway             ● Online
Evolution API           ● Online
WhatsApp                ● Conectado
MapOS API               ● Online

Fila pendente            2
Falhas                    0
Último webhook           há 2 min
Última mensagem          há 3 min
Último evento MapOS      há 4 min
```

Botões:

```text
Ativar/desativar integração
Pausar respostas
Pausar notificações
Testar conexão
```

---

# 47. Aba — Conversas

Exibir:

```text
cliente
telefone
estado
Human Lock
última atividade
últimas mensagens
```

Origem:

```text
CLIENT
BOT
HUMAN
SYSTEM
```

Ações:

```text
Retomar bot
Pausar 30 min
Pausar 2 h
Pausar 24 h
Pausar indefinidamente
Ativar/desativar respostas
Ativar/desativar notificações
```

Não precisa inicialmente ser um cliente WhatsApp completo.

É uma interface de monitoramento e controle.

---

# 48. Aba — Pré-atendimentos

Exibir:

```text
Novo
Incompleto
Pronto
Em revisão
Aprovado
Descartado
```

Permitir:

- abrir draft;
- editar;
- aprovar;
- rejeitar;
- vincular cliente;
- consultar conversa;
- verificar duplicidade.

---

# 48.1. Aba — Logística

Adicionar somente na Fase 8.1, dentro do painel central já previsto. O nome deve
ser **Logística**, pois reúne PICKUP e DELIVERY.

Visualizações:

- pendentes de confirmação;
- hoje, amanhã e próximos dias;
- agrupamento por data, rota, janela e appointment;
- filtros por operação e estado;
- capacidade da janela e do dia;
- localização confirmada e nível de precisão, sem expor coordenadas em listas;
- vínculos para conversa, intake e OS quando existirem.

Ações explícitas e auditadas:

```text
Confirmar
Reagendar
Cancelar
Marcar coletado/entregue
Abrir intake
Abrir OS
Abrir conversa
Abrir localização
```

A configuração administrativa da mesma aba deverá permitir gerenciar zonas,
rotas, relação rota-zona, blackouts, capacidade, classes de equipamento e
perfis de transporte. A UI MapOS consome a Admin API do Gateway; não acessa o
banco `tecnina_bot`.

---

# 49. Aba — Regras de Status

Permitir:

```text
notificar sim/não
status público
template
preview
prioridade
grupo de consolidação
```

Destacar status MapOS sem regra.

---

# 50. Aba — Templates

Permitir:

- editar;
- salvar;
- preview;
- validar placeholders;
- restaurar default.

Criar preview com dados fictícios.

---

# 51. Aba — Logs/Falhas

Exibir:

```text
event_id
message_id
OS
cliente quando apropriado
horário
operação
tentativas
status
erro sanitizado
```

Ação:

```text
Tentar novamente
```

---

# 52. Aba — Configurações

Exibir/configurar:

```text
Bot Gateway URL
Evolution instance
Portal URL
Human Lock default
Debounce
Retenção de logs
Flags globais
```

Segredos:

- mascarados;
- nunca exibidos completos;
- nunca logados.

---

# 53. Override por cliente

Tabela:

```text
client_overrides
```

Campos:

```text
client_id
auto_reply_enabled
status_notifications_enabled
manual_mode
pause_until
updated_by
updated_at
```

Configuração global OFF sempre ganha da configuração individual.

---

# 54. Admin API do Bot Gateway

Endpoints internos sugeridos:

```text
GET  /admin/health
GET  /admin/status

GET  /admin/conversations
GET  /admin/conversations/{id}
POST /admin/conversations/{id}/pause
POST /admin/conversations/{id}/resume
PUT  /admin/conversations/{id}/settings

GET  /admin/messages

GET  /admin/intakes
GET  /admin/intakes/{id}
PUT  /admin/intakes/{id}
POST /admin/intakes/{id}/approve
POST /admin/intakes/{id}/reject

GET  /admin/settings
PUT  /admin/settings

GET  /admin/status-rules
PUT  /admin/status-rules/{id}

GET  /admin/templates
PUT  /admin/templates/{id}

GET  /admin/queue
POST /admin/queue/{id}/retry

GET  /admin/logistics
GET  /admin/logistics/{id}
POST /admin/logistics/{id}/confirm
POST /admin/logistics/{id}/reschedule
POST /admin/logistics/{id}/cancel
POST /admin/logistics/{id}/complete

GET  /admin/logistics/zones
POST /admin/logistics/zones
PUT  /admin/logistics/zones/{id}

GET  /admin/logistics/routes
POST /admin/logistics/routes
PUT  /admin/logistics/routes/{id}

GET  /admin/logistics/blackouts
POST /admin/logistics/blackouts
PUT  /admin/logistics/blackouts/{id}

GET  /admin/logistics/capacity
GET  /admin/logistics/availability
GET  /admin/location-requests/{id}
```

Não expor publicamente.

MapOS deve chamar server-side.

As rotas públicas mínimas de captura/confirmação por token pertencem a uma
superfície separada da Admin API. Elas não aceitam IDs internos previsíveis e
nunca recebem o token administrativo MapOS ↔ Gateway.

---

# 55. Autenticação MapOS ↔ Bot Gateway

Usar:

- token interno;
- secret em variável de ambiente;
- rede interna/restrita;
- TLS quando tráfego sair da rede privada.

Não colocar segredo em JS do navegador.

---

# 56. Fila

Estados:

```text
PENDING
PROCESSING
SENT
FAILED
```

Campos mínimos:

```text
id
event_id
conversation_id
aggregate_type
aggregate_id
os_id nullable
logistics_appointment_id nullable
message_type
payload
attempts
next_attempt_at
last_error
status
created_at
updated_at
```

O payload deve conter apenas dados necessários ao template atual. Uma
notificação logística pode levar janela e link temporário de mapa/localização,
mas não deve carregar coordenadas completas ou endereço integral sem
necessidade.

---

# 57. Retry

Aplicar backoff.

Exemplo conceitual:

```text
1 min
5 min
15 min
1 h
```

Configuração ajustável.

Após limite:

```text
FAILED
```

Manter dead-letter lógica.

Permitir retry manual.

---

# 58. Idempotência de envio

Evitar duplicidade em:

- webhook repetido;
- restart;
- timeout HTTP;
- resposta perdida;
- retry;
- clique duplicado;
- job concorrente.

Gerar chave idempotente por evento/ação.

---

# 59. Observabilidade

Criar:

```text
GET /health/live
GET /health/ready
```

`ready` deve testar:

- banco do bot;
- configuração;
- MapOS;
- Evolution quando apropriado.

Logs estruturados.

Usar:

```text
correlation_id
event_id
conversation_id
```

---

# 60. Logs sensíveis

Nunca logar:

```text
senha
token
cookie
Authorization
CPF completo
documento completo quando desnecessário
código secreto
token de localização
latitude/longitude exatas
endereço ou referência logística sem necessidade diagnóstica
```

Logs logísticos devem usar `appointment_id`, `location_request_id` e códigos de
erro sanitizados. Coordenadas e token bruto não entram em logs estruturados,
traces, métricas ou correlation IDs.

---

# 61. Retenção

Log e mensagens não devem ser armazenados indefinidamente sem necessidade.

Criar retenção configurável.

Exemplo inicial:

```text
30 dias
```

Rever conforme necessidade operacional.

Coordenadas e detalhes de acesso ao local exigem política própria de retenção e
eliminação após a finalidade operacional/auditoria aplicável; não herdar
automaticamente a retenção mais longa de mensagens ou da OS.

---

# 62. NLU futuro

Não implementar no início.

Interface:

```python
class IntentEngine:
    def classify(self, message):
        ...
```

Primeiro:

```text
DeterministicIntentEngine
```

Depois:

```text
SklearnIntentEngine
```

---

# 63. Stack NLU prevista

```text
FastAPI
scikit-learn
TfidfVectorizer
char n-grams
LogisticRegression
```

Sem:

```text
LLM
embeddings
GPU
vector database
Rasa
```

Adicionar somente quando o sistema determinístico estiver estável.

---

# 64. Entidades determinísticas

Usar regex + normalização + validação para:

```text
os_id
protocolo
telefone
CPF
CNPJ
valor
```

Não usar modelo estatístico para entidade que possui gramática clara.

---

# 65. Placeholder antes da classificação

Exemplo:

```text
"queria ver como está a OS 4821"
```

normalizar:

```text
"queria ver como está a OS <OS_ID>"
```

O classificador deve aprender intenção, não números específicos.

---

# 66. Intents futuras

Lista inicial:

```text
consultar_status_os
consultar_prazo_os
consultar_valor_os
abrir_solicitacao
consultar_garantia
consultar_endereco
consultar_horario
formas_pagamento
falar_com_atendente
saudacao
fallback
```

Manter lista pequena.

---

# 67. Política de confiança futura

Valores iniciais, sujeitos a calibração:

```text
confidence >= 0.75
→ executar intenção

0.45 <= confidence < 0.75
→ pedir confirmação

confidence < 0.45
→ fallback / humano
```

Nunca executar operação sensível apenas porque confidence é alta.

Exemplo:

```text
intent = consultar_status_os
confidence = 0.96
os_id = null
```

Não consultar.

Perguntar:

```text
Qual o número da sua OS?
```

---

# 68. Corpus NLU

Estrutura:

```text
nlu/
├── corpus/
│   ├── consultar_status_os.txt
│   ├── consultar_prazo_os.txt
│   ├── garantia.txt
│   ├── abrir_solicitacao.txt
│   └── human_takeover.txt
│
├── train.py
├── evaluate.py
└── model/
    └── intent.joblib
```

Pipeline:

```text
corpus
→ treino
→ validation set
→ confusion matrix
→ threshold tests
→ serialização
→ Docker image
```

---

# 69. Segurança de dados

Princípios:

- minimização;
- autorização proporcional ao risco;
- auditabilidade;
- não revelar informação de terceiros;
- preferir Portal para dados sensíveis.

Não transformar o WhatsApp em substituto do login do Portal.

---

# 70. Edge cases obrigatórios

## Telefone

- com `+55`;
- sem `+55`;
- com máscara;
- sem máscara;
- com nono dígito;
- legado sem nono;
- estrangeiro;
- inválido;
- telefone compartilhado;
- duplicado no MapOS;
- número reciclado.

## WhatsApp

- webhook duplicado;
- mensagem antiga recebida fora de ordem;
- mensagem de grupo;
- mídia sem texto;
- mensagem apagada;
- reação;
- mensagem vazia;
- evento desconhecido;
- payload incompleto;
- instância desconectada;
- QR expirado;
- Evolution reiniciada.

## Human Takeover

- humano responde antes do bot;
- humano responde durante debounce;
- humano responde durante intake;
- mensagem do bot chega como `from_me`;
- humano continua conversando por mais de 30 min;
- pausa manual;
- expiração do lock;
- lock renovado;
- bot reinicia durante lock.

## MapOS

- MapOS indisponível;
- API muda;
- endpoint retorna 404;
- cliente não existe;
- OS não existe;
- OS pertence a outro cliente;
- cliente sem telefone;
- status sem regra;
- status alterado rapidamente várias vezes;
- OS salva, mas envio falha.

## Fila

- retry após timeout;
- Evolution responde mas ACK se perde;
- restart durante PROCESSING;
- job executado duas vezes;
- mensagem enviada mas status local não atualizado;
- falha permanente.

## Intake

- cliente abandona fluxo;
- cliente começa intake e depois pede atendente;
- cliente envia dados fora de ordem;
- cliente já existe;
- telefone corresponde a vários clientes;
- draft duplicado;
- dois operadores aprovam;
- botão Aprovar clicado duas vezes;
- MapOS cria cliente mas falha ao criar OS;
- MapOS cria OS mas resposta HTTP é perdida;
- draft expira;
- usuário retoma dias depois.

## Logística — zona, agenda e equipamento

- cidade/bairro não atendido, ambíguo ou desativado;
- CEP desconhecido, inválido ou na fronteira entre regras;
- endereço incompleto, divergente do MapOS ou cliente sem endereço;
- zona válida sem rota compatível;
- nenhuma janela disponível, horário passado ou rota desativada;
- pedido após cutoff ou sem antecedência mínima;
- feriado, blackout total/parcial e exceção que altera horário/capacidade;
- capacidade da janela disponível, mas capacidade diária esgotada;
- slot fica lotado entre solicitação e confirmação;
- duas confirmações concorrentes disputam a última capacidade;
- confirmação/reagendamento/cancelamento repetido;
- COMPACT, MEDIUM, BULKY, mistura de classes e vários equipamentos;
- quota por classe esgotada ou perfil de transporte incompatível;
- FREE, FIXED_FEE futuro, MANUAL_QUOTE e UNAVAILABLE;
- pickup sem OS e associação posterior;
- uma OS com múltiplos movimentos;
- pickup e delivery em locais/regras de preço diferentes;
- OS cancelada com appointment pendente;
- falha de entrega, nova tentativa e retirada pelo cliente;
- mudança de localização/equipamento após confirmação altera elegibilidade.

## Localização

- permissão GPS negada, navegador incompatível, timeout ou baixa precisão;
- coordenada antiga/cache, fora da faixa ou distante do local pretendido;
- abertura no desktop e fallback pelo WhatsApp/endereço manual;
- token inválido, expirado, revogado, reutilizado ou encaminhado;
- duas submissões concorrentes e confirmação repetida;
- captura corrigida e request anterior marcado `SUPERSEDED`;
- ajuste manual de pin e mensagem WhatsApp Location;
- endereço MapOS divergente do ponto logístico;
- coordenada correta com número/complemento/referência incorretos;
- latitude/longitude, complemento e referência maliciosos;
- alteração do ponto após appointment confirmado;
- ausência de PII no URL, logs, métricas, erros e referrer.

## Templates

- placeholder inválido;
- placeholder ausente;
- valor null;
- texto excessivamente longo;
- caracteres especiais;
- emoji;
- quebra de linha;
- template sem portal URL.

---

# 71. Testes unitários obrigatórios

## Normalização de telefone

Testar:

```text
+55
sem +55
máscaras
DDD
9º dígito
alias legado
internacional
inválido
```

## Webhook

```text
normal
duplicado
from_me
grupo
desconhecido
incompleto
```

## Human Lock

```text
manual ativa lock
bot não ativa lock
renovação
expiração
manual infinito
silêncio durante lock
```

## Status

```text
mudou
não mudou
sem regra
notify=false
notify=true
```

## Fila

```text
offline
retry
sucesso posterior
duplicidade
restart
```

## Latest-wins

```text
3 mudanças
→ 1 mensagem final
```

## Template

Usar snapshot tests.

## Autenticação

```text
telefone único
duplicado
desconhecido
alias com 9
legado
OS de outro cliente
```

## Intake

```text
draft
resume
pause
approve
reject
duplicate approval
existing customer
duplicate customer
```

## Logística e localização

```text
matching de zona por cidade/bairro/CEP
precedência de blackout/exceção
geração de slots no timezone configurado
cutoff e antecedência mínima
operação permitida por rota
elegibilidade por classe/perfil de transporte
capacidade total, por classe, por janela e por dia
transições válidas e eventos fora de ordem
idempotência de confirm/reschedule/cancel/complete
latest-wins com precedência de CANCELLED
token hash, expiração, revogação e consumo na confirmação
latitude/longitude válidas e threshold de accuracy
fallback e superseded location request
sanitização de complemento/referência
ausência de token/coordenadas em logs
```

---

# 72. Fake servers para testes

Criar:

```text
FakeEvolutionServer
FakeMapOSServer
```

Fluxo:

```text
FakeMapOS
→ Bot
→ FakeEvolution
```

Fluxo inverso:

```text
FakeEvolution
→ Bot
→ FakeMapOS
→ Bot
→ FakeEvolution
```

---

# 72.1. Testes integrados da Fase 8.1

Executar com MySQL compatível com produção, migrations Alembic e relógio
controlável:

- criação, associação posterior à OS e múltiplos appointments;
- cálculo zona → rota → operação → janela → capacidade;
- precedência de blackout e conversão UTC/timezone;
- dois confirms simultâneos sobre a última capacidade;
- retry/clique duplo em confirm, reschedule, cancel e complete;
- evento fora de ordem sem regressão de estado;
- persistência da notificação com Evolution indisponível;
- latest-wins após vários reagendamentos e cancelamento;
- Admin API autenticada e MapOS sem acesso SQL ao Gateway.

Para `localizacao.tecnina.com`, adicionar testes de componente/navegador com
geolocalização simulada:

- HTTPS/secure context e viewport mobile;
- token válido, inválido, expirado, revogado e já consumido;
- sucesso, permissão negada, timeout, baixa precisão e recaptura;
- confirmação visual, ajuste manual do pin e fallback desktop;
- validação de coordenadas e sanitização de número/complemento/referência;
- submissões concorrentes/idempotentes;
- ausência de PII no URL e de token/coordenadas em logs/referrer;
- headers `no-store`, CSP e `no-referrer`;
- rate limiting.

---

# 73. Contract tests do MapOSAdapter

Obrigatórios para atualização upstream.

Testar:

```text
autenticação API
get client
find client
get OS
get services
create client
create OS
status retrieval
```

Quando atualizar MapOS:

```text
merge upstream
→ contract tests
```

Se API mudou:

```text
FAIL
```

antes de produção.

---

# 74. Testes E2E em staging

## Caso 1

Alterar status de OS real de teste.

Esperar:

```text
evento
fila
mensagem
log
```

## Caso 2

Evolution offline.

Alterar OS.

Esperar:

```text
MapOS salva normalmente
fila pendente
Evolution volta
retry
mensagem enviada
```

## Caso 3

Operador responde manualmente.

Esperar:

```text
HUMAN_TEMPORARY
```

Cliente responde.

Bot permanece silencioso.

## Caso 4

Alterar OS durante Human Lock.

Mensagem não interrompe atendimento.

## Caso 5

Lock termina.

Enviar estado consolidado.

## Caso 6

Cliente com automação desativada.

Nenhuma mensagem.

## Caso 7

Integração global OFF.

Nenhuma automação.

## Caso 8

Pré-atendimento.

Cliente novo:

```text
WhatsApp
→ draft
→ painel
→ aprovação
→ cliente/OS
```

## Caso 9 — PICKUP

```text
cliente solicita no Intake
→ zona/elegibilidade
→ localização confirmada
→ preferência de janela
→ operador confirma
→ WhatsApp confirma
→ equipamento coletado
→ appointment associado à OS
```

## Caso 10 — DELIVERY

```text
OS liberada por ação explícita
→ local de entrega confirmado
→ operador confirma
→ WhatsApp confirma
→ equipamento entregue
```

## Caso 11 — GPS impreciso

```text
captura com accuracy inadequada
→ não confirmar silenciosamente
→ recaptura ou fallback
→ cliente confirma o ponto válido
```

## Caso 12 — Capacidade concorrente

Dois clientes disputam a última capacidade. Somente uma confirmação
transacional vence; o outro appointment permanece pendente ou exige
reagendamento.

## Caso 13 — Human Lock durante agenda

Cliente escolhe janela e o operador responde manualmente. O Bot cancela a
resposta pendente, para de oferecer horários/localização e preserva os dados já
coletados.

## Caso 14 — Evolution offline após confirmação

A confirmação logística persiste; a notificação fica na fila. Quando a
Evolution retorna, enviar apenas o estado atual, respeitando cancelamento e
latest-wins.

---

# 75. Testes de segurança

- Admin API sem token → 401/403.
- Token inválido → rejeitar.
- Replay webhook → não duplicar.
- SQL injection → bloquear.
- Cliente A tentando OS B → negar.
- Secrets não aparecem em logs.
- Dados pessoais minimizados.
- Template injection → bloquear.
- Path traversal em anexos futuros → bloquear.
- Rate limit em autenticação.
- Limite de tentativas de código.
- Token de localização sem PII, aleatório, expirável, revogável e armazenado
  somente como hash.
- Token inválido/expirado/reutilizado → resposta genérica, sem revelar vínculo.
- Página de localização exige HTTPS, rate limit, CSP, `no-store` e
  `Referrer-Policy: no-referrer`.
- Latitude/longitude, número, complemento e referência são validados e
  sanitizados.
- Coordenadas e token não aparecem em URL de terceiros, logs ou métricas.
- Submissão/confirm duplicados não criam registros nem transições duplicadas.

---

# 76. Testes de compatibilidade upstream

Sempre que houver atualização MapOS:

```text
MapOS core tests
MapOS API contract tests
TecNina integration tests
Portal do Cliente tests
OS event tests
Panel tests
```

---

# 77. Estratégia Git

Remote:

```text
upstream
→ projeto original MapOS

origin
→ fork TecNina
```

Atualização:

```text
git fetch upstream
```

Criar:

```text
update/mapos-X.Y
```

Fazer merge/rebase somente nessa branch.

Executar testes.

Somente depois integrar à branch principal.

---

# 78. Commits

Manter commits pequenos.

Exemplo:

```text
feat(tecnina-whatsapp): add integration admin controller

feat(tecnina-whatsapp): add bot gateway client

feat(tecnina-whatsapp): add OS event bridge

feat(tecnina-whatsapp): add intake review panel

test(tecnina-whatsapp): add MapOS contract tests
```

Evitar commits enormes misturando temas.

---

# 79. Manifesto de customizações

Criar:

```text
docs/TECNINA_CUSTOMIZATIONS.md
```

Manter:

```text
Arquivos upstream modificados
Motivo
Data
Dependência
Alternativa avaliada
```

Também listar:

```text
Arquivos exclusivos TecNina
Integrações externas
Endpoints
Migrations
```

---

# 80. Regras para agente de código

O agente deve:

1. Trabalhar uma fase por vez.
2. Não implementar fases futuras antecipadamente.
3. Não refatorar código sem relação direta.
4. Não alterar layout desnecessariamente.
5. Não remover comportamento existente.
6. Não alterar API existente sem necessidade.
7. Preferir arquivos novos.
8. Antes de modificar upstream, justificar.
9. Manter testes.
10. Manter documentação.
11. Não fazer commit/push sem solicitação explícita.
12. Ao encontrar diferença entre especificação e código real, parar e explicar.
13. Nunca inventar estrutura do MapOS sem inspecionar.
14. Priorizar mudanças pequenas e reversíveis.
15. Não misturar alterações de infra, UI e domínio em uma única fase sem necessidade.

Ao final de cada fase:

```text
- arquivos alterados;
- arquivos criados;
- decisões;
- testes executados;
- resultado dos testes;
- riscos;
- pendências;
- alterações upstream;
```

Depois parar e aguardar autorização.

---

# 81. Fase 0 — Discovery obrigatório

**Nenhum código deve ser alterado.**

Inspecionar:

- versão exata do MapOS;
- estrutura real do fork;
- API disponível;
- endpoints cliente;
- endpoints OS;
- endpoints serviços;
- autenticação;
- migrations;
- hooks;
- libraries;
- menu;
- views;
- todos os caminhos que alteram status da OS;
- transactions existentes;
- configuração de WhatsApp já existente;
- sistema de e-mails;
- Portal do Cliente;
- schema da OS;
- viabilidade de trigger MySQL;
- payload real da Evolution instalada;
- comportamento `from_me`;
- IDs das mensagens enviadas.

Entregar relatório.

Só continuar após aprovação.

---

# 81.1 — Decisões confirmadas na Fase 0

Esta seção consolida as decisões obtidas pela inspeção do código real da fork.
Ela prevalece sobre exemplos conceituais anteriores quando houver diferença de
detalhe, sem alterar os princípios arquiteturais deste documento.

## Ambiente confirmado

```text
MapOS:                  4.54.0
Branch:                 master
origin/master:          01dc35b
PHP mínimo:             8.4
PHP do container:       8.5
MySQL padrão Docker:    8.4
API MapOS:              ativa somente com API_ENABLED=true
```

### Evidências do ambiente real da TecNina

Uma inspeção remota, somente leitura, foi realizada no host autorizado `opi5`
em 2026-09-04. Nenhuma configuração, container, imagem, rede, volume, banco ou
aplicação foi alterado.

```text
hostname:               orangepi5pro
usuário SSH:             orangepi
arquitetura:             aarch64
resource ID MapOS:       l29tpqli0yt1usg25981aouz
rede Docker MapOS:       l29tpqli0yt1usg25981aouz
DNS interno HTTP:        nginx
DNS interno PHP-FPM:     php-fpm
DNS interno MySQL:       mysql
PHP efetivo:             8.5.10
MySQL efetivo:           8.4.11
API_ENABLED em produção: false
```

Os endereços IP observados nos containers não são contrato de integração e não
devem ser persistidos, pois podem mudar a cada deploy. Quando os serviços
estiverem ligados à mesma rede Docker, deverão ser usados os aliases DNS
internos. O Gateway continuará proibido de acessar diretamente o MySQL do
MapOS: o alias `mysql` foi registrado apenas como evidência operacional.

A API do MapOS está desabilitada no ambiente real. Isso não deve ser alterado
durante a Fase 0. Antes dos testes de integração de uma fase futura, será
necessário habilitá-la deliberadamente e validar que apenas os endpoints e
controles autorizados fiquem expostos. Enquanto `API_ENABLED=false`, o
`health/ready` do Gateway deverá reportar a integração MapOS como indisponível,
sem crash e sem loop de retry infinito.

O remote oficial foi identificado de forma inequívoca no próprio repositório e
configurado somente para consulta:

```text
upstream:   https://github.com/RamonSilva20/mapos.git
merge-base: 2588066f485e71357b24d2f563a9102dfbf08dfb
divergência master...upstream/master: 40 commits à frente, 0 atrás
```

O `git fetch upstream` foi executado sem merge, rebase, cherry-pick ou alteração
da branch atual. No momento da descoberta, `upstream/master` também corresponde
à versão 4.54.0.

Desde o merge-base foram identificados aproximadamente:

```text
40 commits próprios
138 arquivos afetados
```

Isso reforça que a integração WhatsApp deve acrescentar o mínimo possível de
alterações em arquivos upstream. A meta específica da integração permanece:

```text
idealmente <= 3 arquivos upstream modificados diretamente
```

Antes de modificar um arquivo upstream, registrar:

- por que a mudança aditiva não é suficiente;
- alternativa avaliada;
- impacto esperado em merges futuros;
- teste de compatibilidade correspondente.

## Arquitetura confirmada

```text
MapOS
  ↕ API / eventos mínimos
TecNina Bot Gateway
  ↕
Evolution API
  ↕
WhatsApp
```

O Bot Gateway integra com o MapOS. Ele não deve morar dentro do MapOS.

Permanecem obrigatórios:

- arquivos, controllers, libraries e views próprios sempre que possível;
- endpoints específicos `/api/bot/*`;
- `MapOSAdapter` como único acesso do Gateway ao MapOS;
- ausência de SQL direto do Gateway no schema do MapOS;
- testes de contrato para detectar mudanças após atualização upstream;
- inventário explícito das customizações.

## API administrativa genérica rejeitada como contrato do Gateway

A API administrativa real usa JWT HS256 com expiração e permissões de usuário.
Ela não será o mecanismo normal de autenticação do Bot Gateway porque isso:

- exigiria armazenar credenciais de funcionário;
- exigiria renovar JWT;
- acoplaria o Gateway ao modelo de sessão/permissão de operadores;
- exporia respostas administrativas mais amplas do que o necessário.

A API genérica de clientes também não será reutilizada como contrato do Bot.
Ela possui consultas com `SELECT *` e não oferece busca exata e normalizada por
telefone como contrato seguro.

Os endpoints `/api/bot/*` deverão:

- usar autenticação interna independente;
- autorizar cada operação explicitamente;
- aplicar whitelist de campos;
- retornar apenas o mínimo necessário;
- nunca retornar password, hash de senha, JWT, cookie ou secret;
- nunca usar `SELECT *`;
- nunca usar busca parcial com `LIKE` como autenticação;
- usar comparação segura do token interno;
- possuir contract tests próprios.

Exemplo de resposta mínima:

```http
GET /api/bot/client/by-phone
```

```json
{
  "matched": true,
  "client_id": 42
}
```

Casos de nenhum resultado e múltiplos resultados devem ser representados sem
revelar nomes ou outros dados de terceiros.

O cenário `API_ENABLED=false` deve ser tratado explicitamente. O Gateway deve
reportar o MapOS como indisponível em readiness, com erro compreensível, sem
crash e sem loop agressivo.

## MapOSAdapter

Toda operação Bot → MapOS deverá passar pelo `MapOSAdapter`:

```text
Bot Gateway
  ↓
MapOSAdapter
  ↓
/api/bot/*
  ↓
MapOS
```

O adapter deve centralizar:

- URL e autenticação interna;
- timeouts;
- serialização;
- tratamento de erros;
- versionamento do contrato;
- normalização das respostas;
- retries apenas quando seguros;
- correlation IDs.

Se uma rota do MapOS mudar, apenas esse adapter deve precisar de ajuste.

## Caminhos reais de criação e alteração de OS

Foram confirmados os seguintes caminhos:

```text
Os::adicionar
Os::editar
Os::faturar

API OsController::index_post
API OsController::index_put

Mine::adicionarOs
ClientOsController::os_post
```

Não adicionar chamadas de integração individualmente nesses controllers. Isso
duplicaria lógica, deixaria caminhos descobertos e aumentaria conflitos com o
upstream.

## Estratégia preferencial: trigger MySQL + outbox MapOS

Para `os.status_changed`, a estratégia preferencial passa a ser:

```text
UPDATE da tabela física de OS
  ↓
AFTER UPDATE TRIGGER
  ↓
OLD.status é diferente de NEW.status?
  ↓ sim
INSERT mínimo em tecnina_integration_outbox
  ↓
endpoint interno MapOS
  ↓
Bot Gateway
```

A trigger deve apenas persistir o evento. Ela nunca deverá:

- chamar HTTP;
- chamar Evolution;
- acessar o banco `tecnina_bot`;
- enviar WhatsApp;
- executar templates ou regras conversacionais.

A outbox deve estar no banco do MapOS. O Gateway continua sem acesso SQL a esse
schema e recebe eventos somente pelo contrato HTTP do MapOS.

## Estrutura conceitual da outbox

Projetar uma outbox com, no mínimo:

```text
id
event_id UUID UNIQUE
event_type
os_id
client_id
old_status
new_status
state
created_at
claimed_at
claim_token
claim_expires_at
acknowledged_at
attempts
last_error
```

Estados iniciais:

```text
PENDING
CLAIMED
ACKNOWLEDGED
```

O payload da outbox não deve carregar endereço, CPF, credencial do aparelho,
anexos, laudos ou outros dados pessoais desnecessários.

## Claim, ACK e idempotência

Não usar o fluxo ingênuo `GET → processar → sent=true`.

Fluxo obrigatório:

```text
Gateway solicita lote
  ↓
MapOS seleciona e faz claim atomicamente
  ↓
MapOS retorna claim_token + eventos
  ↓
Gateway persiste cada event_id com UNIQUE
  ↓
Gateway envia ACK
```

Regras:

- o claim possui prazo de expiração;
- claim expirado pode ser recuperado com segurança;
- ACK repetido deve ser idempotente;
- token de claim inválido não pode reconhecer eventos;
- se o ACK falhar, o MapOS pode entregar novamente;
- o Gateway deve ignorar novo processamento do mesmo `event_id`;
- persistência do evento no Gateway ocorre antes do ACK;
- mesmo com um worker, o desenho deve ser seguro para concorrência.

## DB_PREFIX e instalação no MapOS

O instalador não pode assumir que a tabela física se chama literalmente `os`.
Antes de criar tabela, índices ou trigger, ele deve resolver o `dbprefix` efetivo
do CodeIgniter.

O instalador específico da integração deverá:

- funcionar com prefixo vazio e não vazio;
- validar a existência da tabela física de OS e da coluna `status`;
- detectar instalação anterior e instalação parcial;
- validar privilégio `TRIGGER` e apresentar erro claro quando ausente;
- detectar trigger com nome conflitante;
- criar a outbox e seus índices idempotentemente;
- não destruir dados existentes;
- não recriar trigger quando a definição já estiver correta;
- permitir modo somente verificação;
- falhar com código diferente de zero quando o estado final estiver incompleto;
- poder ser executado repetidamente sem erro ou duplicação.

Não depender do versionamento global de migrations do MapOS para este módulo.

```text
Schema MapOS da integração: instalador idempotente próprio
Schema tecnina_bot:          Alembic
```

### Estado real do banco MapOS em produção

A inspeção somente leitura confirmou:

```text
DB_HOSTNAME:                         mysql
DB_DATABASE:                         mapos
DB_USERNAME:                         mapos
DB_PREFIX:                           vazio
tabela física de OS:                 os
triggers existentes na tabela os:   0
tecnina_integration_outbox:          ausente
```

O `SHOW GRANTS FOR CURRENT_USER` retornou `mapos@%` com `ALL PRIVILEGES` no
schema `mapos` e apenas `USAGE` global. Portanto, o usuário efetivo possui o
privilégio `TRIGGER` necessário dentro desse schema. O privilégio aparece por
meio de `ALL PRIVILEGES`, e não como uma concessão `TRIGGER` isolada.

Não existe hoje trigger conflitante na tabela física `os`, nem evidência da
outbox ou de uma instalação parcial anterior. Esses fatos descrevem o estado
atual e não dispensam o instalador futuro de resolver `DB_PREFIX`, validar os
privilégios e detectar conflitos novamente em toda execução.

## Portal do Cliente

O comportamento atual será mantido. Clientes já cadastrados continuam podendo
criar OS pela Área do Cliente e pela API do cliente.

Passam a coexistir as origens conceituais:

```text
ADMIN
CLIENT_PORTAL
WHATSAPP_INTAKE
```

Não adicionar coluna de origem no MapOS sem necessidade operacional comprovada.
A relação de intake deve permanecer preferencialmente no banco do Gateway.

## Intake futuro e credencial do aparelho

O intake via WhatsApp não solicitará senha, PIN ou padrão de desbloqueio.

Quando uma aprovação futura criar uma OS:

```text
credencial_tipo = nao_informada
```

Esse é o valor equivalente confirmado no schema/código atual. A relação deverá
ser mantida no banco do Bot:

```text
intake_id
mapos_client_id
mapos_os_id
```

Se for útil à operação, criar uma anotação administrativa simples:

```text
OS criada a partir do pré-atendimento WhatsApp #123.
Credencial do equipamento ainda não informada.
```

A credencial real será coletada durante a triagem física. Não inventar senha,
não tratar string vazia como senha e não registrar `SEM SENHA` sem confirmação
do cliente/atendente.

## Painel administrativo futuro

Local inicial confirmado:

```text
Configurações → WhatsApp
Permissão: cSistema
```

Não criar nesta etapa um novo sistema de permissões. O painel continuará
centralizado e poderá futuramente migrar para `Integrações` caso outras
integrações justifiquem essa hierarquia.

## Evolution API — pendência crítica

Não iniciar a Fase 1 antes de validar a instalação real da Evolution.

### Resultado da inspeção do host `opi5`

Em 2026-09-05, a stack independente da Evolution foi implantada manualmente
pelo responsável no Coolify. A inspeção somente leitura confirmou:

```text
release estável:        2.3.7
imagem:                 evoapicloud/evolution-api:v2.3.7
plataforma:             linux/arm64
digest ARM64:           sha256:0e5d84f45b390e1d659500c9a98bfa2a53be28a341fbc0864966b77485f2a0c5
image ID executado:     sha256:5cf854f2eb3af149ed87841c2c882d8eecff68d278aa87e765e88f7541a36409
resource ID Coolify:    smibywui5qlab7vcb9ngyavt
URL pública:            https://wa-api.tecnina.com
Manager:                https://wa-api.tecnina.com/manager
Cloudflare Tunnel:      wa-api.tecnina.com -> http://localhost:80
rota Coolify interna:   http://wa-api.tecnina.com:8080
```

Os três serviços exclusivos estão saudáveis:

```text
evolution-api       2.3.7
evolution-postgres  15.19-bookworm
evolution-redis     7.4.11-alpine3.21
```

As migrations iniciais da Evolution foram aplicadas com sucesso em seu
PostgreSQL exclusivo. PostgreSQL e Redis não publicam portas no host. A API
respondeu publicamente com HTTP 200, versão `2.3.7` e client name
`tecnina-evolution`; o Manager também está acessível.

Como a versão 2.3.7 rejeita requisições sem cabeçalho `Origin` quando recebe
somente uma origem explícita, o ambiente usa o default oficial
`CORS_ORIGIN=*`. Esse ajuste não substitui autenticação; as operações protegidas
continuam exigindo a API key global. O valor da key não foi lido nem registrado.

O link `manager` retornado pela rota raiz usa `http` porque a aplicação enxerga
o trecho interno Cloudflare Tunnel -> Coolify. O endereço canônico para o
operador continua sendo HTTPS.

Após a implantação, a instância `tecnina` foi criada no Evolution Manager. Uma
consulta interna e sanitizada confirmou `status=open`, integração
`WHATSAPP-BAILEYS` e pareamento ativo. Não havia webhook configurado e o canal
WebSocket por instância também estava desativado no momento da inspeção.

A captura controlada foi expressamente autorizada e executada em 2026-09-05.
Foram habilitados temporariamente apenas os eventos necessários no WebSocket da
instância. O coletor executou em memória dentro do próprio container, ignorou
mensagens alheias aos textos de teste, não utilizou serviço externo e não
persistiu payload bruto. Ao final, o processo foi encerrado e o WebSocket foi
restaurado para `enabled=false`, com lista de eventos vazia.

### Evidências sanitizadas da Evolution 2.3.7

#### A — CLIENT envia texto para a TecNina

```json
{
  "event": "messages.upsert",
  "instance": "tecnina",
  "data": {
    "key": {
      "remoteJid": "[REDACTED]@s.whatsapp.net",
      "remoteJidAlt": "[REDACTED]@s.whatsapp.net",
      "fromMe": false,
      "id": "ID#CLIENT",
      "addressingMode": "lid"
    },
    "status": "DELIVERY_ACK",
    "message": { "conversation": "[REDACTED_TEST_CONTENT]" },
    "messageType": "conversation",
    "messageTimestamp": 1788605168,
    "source": "android"
  },
  "sender": "[REDACTED]@s.whatsapp.net"
}
```

Localização dos campos:

```text
event        -> event
instance     -> instance
remote_jid   -> data.key.remoteJid
from_me      -> data.key.fromMe
message_id   -> data.key.id
timestamp    -> data.messageTimestamp
texto        -> data.message.conversation
tipo         -> data.messageType
```

#### B — HUMAN envia texto pelo número da TecNina

A resposta manual feita pelo WhatsApp conectado produziu:

```json
{
  "event": "messages.upsert",
  "instance": "tecnina",
  "data": {
    "key": {
      "remoteJid": "[REDACTED]@s.whatsapp.net",
      "fromMe": true,
      "id": "ID#HUMAN"
    },
    "status": "SERVER_ACK",
    "message": { "conversation": "[REDACTED_TEST_CONTENT]" },
    "messageType": "conversation",
    "source": "android"
  }
}
```

O valor `fromMe=true` sozinho não identifica o Bot. Como esse ID não havia sido
registrado por um envio do Gateway, o evento deve ser classificado como HUMAN.

#### C — BOT envia texto pela REST API

O `POST /message/sendText/tecnina` retornou HTTP 201:

```json
{
  "key": {
    "remoteJid": "[REDACTED]@s.whatsapp.net",
    "fromMe": true,
    "id": "ID#BOT"
  },
  "status": "PENDING",
  "message": { "conversation": "[REDACTED_TEST_CONTENT]" },
  "messageType": "conversation",
  "source": "web"
}
```

O evento correspondente foi:

```json
{
  "event": "send.message",
  "instance": "tecnina",
  "data": {
    "key": {
      "remoteJid": "[REDACTED]@s.whatsapp.net",
      "fromMe": true,
      "id": "ID#BOT"
    },
    "status": "PENDING",
    "messageType": "conversation",
    "source": "web"
  }
}
```

O fingerprint calculado do ID da resposta REST foi exatamente igual ao do ID
recebido em `send.message`. Logo, a regra comprovada para o Human Lock é:

```text
from_me=true + provider_message_id previamente registrado -> BOT
from_me=true + provider_message_id desconhecido            -> HUMAN
```

O envio REST não produziu `messages.upsert` durante a janela observada. A
inspeção do código oficial da versão 2.3.7 confirmou que o caminho de envio usa
`Events.SEND_MESSAGE`; portanto, o adapter deve consumir `send.message` para
correlacionar mensagens geradas pelo Bot e não deve presumir que todo envio
próprio aparecerá como `messages.upsert`.

#### Grupos, duplicidade e tipos

- Conversa individual observada: `remoteJid` termina em `@s.whatsapp.net`.
- Grupo: identificar por `remoteJid` terminado em `@g.us`. Não houve envio real
  em grupo; o formato e a checagem foram confirmados no código oficial 2.3.7,
  que usa `isJidGroup`/`@g.us`. O MVP deve ignorar esses eventos antes de buscar
  cliente ou revelar qualquer OS.
- Cada evento controlado apareceu uma única vez (`duplicate_ordinal=1`). Não foi
  observada duplicação do mesmo `message_id` e tipo durante a janela do teste.
- `messages.upsert` e `send.message` são eventos semanticamente distintos, ainda
  que ambos carreguem a estrutura `data.key.id`.
- Atualizações posteriores podem chegar como `messages.update`; não devem ser
  confundidas com uma nova mensagem. A chave de idempotência precisa considerar
  provedor, instância, tipo do evento, ID da mensagem e, para updates legítimos,
  o estado/versão relevante.
- Texto simples apareceu como `messageType=conversation` e conteúdo em
  `message.conversation`. O DTO deve aceitar texto ausente e preservar
  `messageType`, pois mídia, reação, remoção e updates possuem estruturas
  diferentes.

Com essas evidências, a pendência da Evolution para a Fase 0 está encerrada.

Registrar, quando disponíveis:

```text
imagem
registry
tag
digest
versão
```

Não definir o adapter somente com base em documentação online. Capturar e
sanitizar os seguintes cenários reais:

### A — CLIENT envia mensagem para a TecNina

Documentar:

```text
event type
instance
remote_jid
sender
from_me
message_id
timestamp
texto
tipo da mensagem
```

### B — HUMAN envia mensagem pelo número da TecNina

Enviar por telefone, WhatsApp Web ou Desktop e documentar o webhook. Toda
mensagem `from_me=true` que não puder ser correlacionada a uma mensagem gerada
pelo Gateway será tratada como intervenção humana.

### C — BOT envia mensagem pela REST API

Guardar a resposta REST sanitizada e o webhook posterior, registrando:

```text
api_request_id quando existir
provider_message_id
remote_jid
instance
status
```

Comprovar se o ID retornado pelo envio corresponde ao `message_id` do webhook.
Se não corresponder, documentar a chave de correlação real antes de implementar
Human Takeover.

## Mensagens geradas pelo Bot

Preparar nas Fases 1 e 2 armazenamento equivalente a:

```text
bot_generated_messages

provider
instance_id
api_request_id
provider_message_id
remote_jid
sent_at
webhook_seen_at
```

Objetivo:

```text
from_me=true + ID conhecido    → BOT, não ativa Human Lock
from_me=true + ID desconhecido → HUMAN, ativa HUMAN_TEMPORARY
```

## Duplicidade, grupos e tipos de mensagem

Durante a captura da Evolution, verificar:

- repetição do mesmo `message_id`;
- eventos de criação/upsert;
- update/status/ACK da mesma mensagem;
- eventos fora de ordem;
- mensagem apagada e reação;
- payload sem texto;
- localização da informação de grupo.

Capturar payload de grupo quando possível. No MVP:

```text
grupo → ignorar automação
```

Para tipos de mensagem:

```text
texto              → processável
imagem/documento   → registrar tipo e usar fallback seguro
áudio               → registrar tipo e usar fallback seguro
reação/apagada      → ignorar automação conforme regra documentada
vazia/desconhecida  → não executar fluxo sensível
```

O Canonical Message DTO somente será fechado depois dessas capturas.

## Sanitização da descoberta

Nunca incluir nos relatórios ou fixtures reais:

- API key, token ou secret;
- telefone/remote JID completo;
- CPF;
- e-mail;
- nome de cliente;
- conteúdo pessoal;
- Authorization, JWT ou cookie.

Manter apenas a estrutura e, quando indispensável à correlação, poucos
caracteres finais de um identificador fictício/sanitizado.

## Testes obrigatórios acrescentados pela Fase 0

### MapOS Outbox e trigger

```text
status não muda                 → nenhum evento
status muda                     → exatamente um evento
alteração pelo painel           → evento
alteração pela API              → evento
alteração por faturamento       → evento
novo caminho na mesma coluna    → evento
```

### DB prefix e instalador

```text
dbprefix vazio                  → nomes físicos corretos
dbprefix não vazio              → nomes físicos corretos
install                         → schema correto
install novamente               → sem erro/duplicação
instalação parcial              → reparo seguro
TRIGGER permitido               → instalação concluída
TRIGGER negado                  → erro claro e estado incompleto detectado
trigger conflitante             → erro claro, sem sobrescrever silenciosamente
```

### Claim/ACK

```text
claim de lote
ACK correto
ACK repetido
claim expirado
reclaim
restart após claim
persistência no Gateway + falha de ACK
reentrega do MapOS
event_id duplicado no Gateway
workers concorrentes
```

### Contratos `/api/bot/*`

```text
sem token       → 401/403
token inválido  → 401/403
token correto   → sucesso
```

As respostas devem ser verificadas explicitamente para garantir ausência de:

```text
password
hash
secret
JWT
cookie
campos não incluídos na whitelist
```

### Busca por telefone

Cobrir:

```text
+55 41 9...
55419...
419...
(41) 9...
legado sem 9
internacional
inválido
único
duplicado
inexistente
```

Nunca usar `LIKE` parcial como autenticação.

### Intake e credencial

```text
aprovação sem credencial
→ OS criada
→ credencial_tipo = nao_informada
→ nenhuma credencial inventada
→ vínculo intake_id/mapos IDs persistido
```

### Human Takeover

```text
from_me=true + ID desconhecido  → HUMAN_TEMPORARY
from_me=true + ID do Bot        → não ativa Human Lock
nova mensagem humana            → renova human_until
timeout                         → retorna para AUTO
HUMAN_MANUAL                    → não expira automaticamente
```

Race condition obrigatória:

```text
cliente envia mensagem
→ Bot prepara resposta
→ humano responde antes do envio
→ Human Lock vence
→ resposta pendente do Bot é cancelada
```

### Debounce

Usar intervalo configurável inicial de 3–5 segundos. Mensagens fragmentadas
devem formar uma única entrada, não quatro fluxos independentes.

### Notificação durante Human Lock

```text
AUTO_REPLY                 → cancelar/silenciar
TRANSACTIONAL_NOTIFICATION → manter pendente
fim do lock                → latest-wins
```

Exemplo latest-wins:

```text
Diagnóstico
→ Orçamento
→ Aguardando aprovação

resultado após o lock:
uma mensagem com "Aguardando aprovação"
```

## Limites confirmados da Fase 1

Depois do encerramento e da aprovação explícita da Fase 0, a Fase 1 ficará
restrita a:

```text
FastAPI e Uvicorn
configuração e validação de ambiente
MySQL tecnina_bot
Alembic
health/live
health/ready
Canonical Message DTO baseado nos payloads reais
EvolutionAdapter
MapOSAdapter skeleton
webhook ingress
deduplicação
persistência de mensagens/eventos
logging estruturado
testes unitários
testes de integração básicos
```

Não implementar na Fase 1:

```text
trigger/outbox MapOS
envio automático de OS
painel MapOS
intake
NLU
autenticação de cliente
templates avançados
Human Lock completo
```

Human Lock permanece na Fase 2.

## Estrutura inicial prevista do Gateway

```text
app/
├── main.py
├── config.py
├── api/
│   ├── evolution_webhook.py
│   ├── mapos_events.py
│   ├── admin.py
│   └── health.py
├── adapters/
│   ├── whatsapp/
│   │   ├── base.py
│   │   └── evolution.py
│   └── mapos/
│       └── adapter.py
├── domain/
│   ├── messages.py
│   ├── events.py
│   └── conversations.py
├── services/
├── repositories/
├── db/
└── tests/
```

Não misturar HTTP do provider, domínio, persistência e integração MapOS no
mesmo módulo.

Variáveis previstas, sempre em `.env.example` sem valores secretos:

```text
DATABASE_URL
MAPOS_BASE_URL
MAPOS_BOT_TOKEN
EVOLUTION_BASE_URL
EVOLUTION_API_KEY
EVOLUTION_INSTANCE
LOG_LEVEL
ENVIRONMENT
```

Todas as chamadas externas devem possuir connect/read timeout explícitos.
Retries automáticos são permitidos somente para operações seguras. POST de
envio WhatsApp exige idempotência/correlação antes de qualquer retry.

Logs estruturados devem prever:

```text
correlation_id
event_id
conversation_id
provider
instance_id
```

Nunca registrar Authorization, API key, password, JWT, CPF completo, hash ou
cookie.

## Health checks

```text
GET /health/live
→ processo está vivo

GET /health/ready
→ configuração válida
→ banco tecnina_bot disponível
→ MapOS disponível e API habilitada
```

O estado da Evolution poderá ser componente separado ou integrar readiness de
acordo com a decisão da Fase 1. Sua indisponibilidade nunca poderá tornar o
MapOS indisponível.

## Documento de customizações

Quando a implementação começar, criar ou atualizar:

```text
docs/TECNINA_CUSTOMIZATIONS.md
```

O documento deverá listar arquivos upstream modificados, motivo, necessidade,
alternativas avaliadas, arquivos novos TecNina, endpoints, migrations,
instaladores e integrações externas. Ele não é criado durante a discovery
puramente documental.

## Critério atualizado para encerrar a Fase 0

A Fase 0 só termina após responder com evidência sanitizada:

1. Qual imagem/tag/digest/versão da Evolution está rodando?
2. Como aparece uma mensagem CLIENT → TecNina?
3. Como aparece uma mensagem HUMAN → cliente?
4. Como aparece uma mensagem BOT → cliente?
5. Onde estão `from_me`, `message_id`, `remote_jid` e `instance`?
6. O ID retornado pelo envio corresponde ao webhook?
7. Como distinguir grupo?
8. Existem eventos duplicados ou eventos diferentes para a mesma mensagem?
9. Como distinguir BOT de HUMAN com segurança?
10. Qual é o `DB_PREFIX` efetivo de produção?
11. O usuário do MapOS possui privilégio `TRIGGER`?
12. Quais triggers já existem na tabela física de OS?

Estado das respostas em 2026-09-05:

```text
Item 1:      respondido; Evolution 2.3.7, imagem/digest ARM64 e pareamento confirmados
Item 2:      respondido; CLIENT = messages.upsert, fromMe=false
Item 3:      respondido; HUMAN = messages.upsert, fromMe=true, ID desconhecido
Item 4:      respondido; BOT = REST 201 + send.message, fromMe=true
Item 5:      respondido; caminhos dos campos documentados nas evidências
Item 6:      respondido; ID da resposta REST corresponde exatamente ao evento
Item 7:      respondido; grupo identificado por remoteJid terminado em @g.us
Item 8:      respondido; nenhuma duplicata na amostra; eventos distintos documentados
Item 9:      respondido; correlação pelo provider_message_id previamente registrado
Item 10:     DB_PREFIX vazio; tabela física de OS = os
Item 11:     sim; ALL PRIVILEGES no schema mapos inclui TRIGGER
Item 12:     nenhuma trigger existente na tabela os
```

**Fase 0 encerrada em 2026-09-05.** Nenhum Gateway, trigger, outbox, schema ou
código funcional da integração foi criado durante a discovery. O início da
Fase 1 continua dependendo de autorização explícita do responsável.

---

# 82. Fase 1 — Fundação do Bot Gateway

Criar:

- projeto FastAPI;
- config;
- DB próprio;
- migrations;
- health endpoints;
- Canonical Message DTO;
- EvolutionAdapter;
- MapOSAdapter;
- webhook;
- deduplicação;
- log;
- testes.

Critério:

```text
webhook entra
→ é normalizado
→ é persistido
→ duplicado é ignorado
```

---

# 83. Fase 2 — Human Takeover

Criar:

- session state;
- AUTO;
- HUMAN_TEMPORARY;
- HUMAN_MANUAL;
- identificação de mensagens do bot;
- detecção humana;
- pause/resume;
- timeout;
- testes.

Critério:

> Operador pode conversar sem o bot interromper.

---

# 84. Fase 3 — Eventos MapOS / Outbox

Criar:

- estratégia escolhida na Fase 0;
- outbox;
- dispatcher;
- idempotência;
- endpoint Bot;
- testes.

Critério:

> Alteração de OS gera evento sem depender da Evolution.

---

# 85. Fase 4 — Notificações

Criar:

- status_rules;
- templates;
- queue;
- retry;
- latest-wins;
- portal URL;
- os.status_changed;
- os.created se seguro;
- testes.

Critério:

> Alterar status envia WhatsApp correto.

Essa é a primeira milestone de produção realmente prioritária.

---

# 86. Fase 5 — Painel MapOS

Criar:

```text
Configurações
→ WhatsApp
```

Implementar:

- visão geral;
- conversas;
- status;
- logs;
- fila;
- regras;
- templates;
- configuração;
- pause/resume.

A aba Logística e suas regras não entram nesta fase; serão acrescentadas na
Fase 8.1 sobre a fundação do painel.

Critério:

> Operador gerencia integração sem abrir Bot Gateway.

---

# 87. Fase 6 — Hardening

Criar:

- auth interna;
- rate limit;
- sanitização;
- retenção;
- métricas;
- retries finais;
- tratamento de timeout;
- testes E2E;
- documentação.

---

# 88. Fase 7 — Pré-atendimento

Criar:

- intake_drafts;
- FSM;
- cliente novo;
- cliente existente;
- coleta progressiva;
- draft persistente;
- resume;
- Human Lock;
- expiração;
- testes.

Critério:

> Novo cliente consegue enviar informações antes do operador assumir.

---

# 89. Fase 8 — Aprovação de Intake no MapOS

Criar:

- aba Pré-atendimentos;
- edit draft;
- duplicate detection;
- vincular cliente;
- aprovar;
- rejeitar;
- criar cliente;
- criar OS;
- idempotência;
- auditoria;
- testes.

Critério:

> Operador revisa e cria dados no MapOS com um clique seguro.

---

# 89.1. Fase 8.1 — Logística, localização e agendamento

Implementar somente depois que Pré-atendimento e Aprovação de Intake estiverem
estáveis:

- domínio logístico único para PICKUP e DELIVERY no `tecnina_bot`;
- migrations Alembic das entidades `logistics_*`, perfis e location requests;
- zonas, rotas, relação rota-zona e blackouts configuráveis;
- timezone IANA e geração de disponibilidade;
- classes de equipamento e perfis de transporte configuráveis;
- elegibilidade e modos FREE, FIXED_FEE, MANUAL_QUOTE e UNAVAILABLE;
- capacidade por janela, dia, total e classe;
- confirmação transacional e protegida contra overbooking;
- appointments antes/depois da OS e múltiplos movimentos por OS;
- coleta progressiva no Intake;
- página pública mobile-first de localização por token opaco;
- Browser Geolocation, confirmação visual, accuracy e fallbacks;
- aba Logística e Admin API do Gateway;
- eventos, notification queue, Human Lock e latest-wins;
- auditoria, idempotência, privacidade, retenção e testes.

Não implementar nesta fase sem necessidade comprovada:

```text
status logístico no MapOS
SQL MapOS -> tecnina_bot
geofencing sofisticado
pagamento/cobrança automática
otimização automática de rota
trânsito em tempo real
tracking GPS
aplicativo nativo
```

Critério:

> O cliente registra uma preferência elegível de coleta ou entrega, confirma o
> ponto quando necessário e o operador consegue confirmar, reagendar, cancelar
> e concluir o movimento com capacidade, privacidade e idempotência, sem
> alterar o estado de reparo da OS.

---

# 90. Fase 9 — Autoatendimento determinístico

Criar:

- menu;
- informações públicas;
- acompanhar reparo via Portal;
- falar com atendimento;
- consultas simples;
- FSM.

Sem NLU.

---

# 91. Fase 10 — Autenticação avançada

Criar:

- phone normalization final;
- código de consulta;
- verification session;
- authorization;
- expiração;
- rate limit.

---

# 92. Fase 11 — NLU opcional

Somente após todas as fases anteriores estarem estáveis.

Criar:

- corpus;
- training;
- evaluation;
- confusion matrix;
- thresholds;
- SklearnIntentEngine;
- integração com FSM.

Não alterar regras de autorização.

---

# 93. O que está fora do escopo inicial

Não implementar agora:

```text
LLM
agente generativo
embeddings
vector DB
análise automática de imagem
áudio/STT
Chatwoot
CRM adicional
multicanal
múltiplos operadores
pagamento pelo WhatsApp
aprovação automática de orçamento
edição automática de dados sensíveis
```

Podem ser avaliados futuramente.

---

# 94. Licenciamento / atribuição visual

Licenciamento de dependências deve ser tratado separadamente da implementação funcional.

Neste feature request:

- não adicionar mensagens de atribuição ao cliente;
- não adicionar rodapé de licença ao WhatsApp;
- não adicionar badge ou publicidade no site;
- não adicionar elemento visual desnecessário no painel;
- não alterar experiência do cliente por esse motivo.

Qualquer requisito jurídico real de redistribuição/licenciamento deve ser analisado separadamente antes de uma distribuição comercial mais ampla.

---

# 95. Critérios de aceite do MVP

O MVP estará pronto quando:

1. MapOS puder ser atualizado futuramente sem forte acoplamento com o bot.
2. Bot Gateway estiver separado.
3. Evolution estiver abstraída por adapter.
4. MapOSAdapter estiver funcionando.
5. Webhooks forem idempotentes.
6. Human Lock funcionar.
7. Mudança de OS gerar evento.
8. Evolution offline não impedir update da OS.
9. Fila possuir retry.
10. Cliente receber notificação.
11. Portal do Cliente aparecer como destino para detalhes.
12. Operador puder pausar automação.
13. Painel MapOS exibir status.
14. Logs de falha existirem.
15. Contract tests do MapOS existirem.
16. Testes E2E do fluxo principal passarem.

---

# 96. Critérios de aceite do Pré-atendimento

Pronto quando:

1. cliente novo puder iniciar contato;
2. bot puder coletar dados;
3. draft persistir;
4. operador puder assumir;
5. Human Lock pausar intake;
6. operador puder revisar draft;
7. duplicidade for detectada;
8. aprovação for idempotente;
9. cliente/OS forem criados somente após aprovação;
10. IDs criados forem registrados.

---

# 96.1. Critérios de aceite da Fase 8.1

A fase logística estará concluída quando:

1. PICKUP e DELIVERY usarem o mesmo domínio e as mesmas abstrações;
2. zonas, rotas, operações permitidas, horários e blackouts forem configuráveis;
3. nenhuma regra pessoal de deslocamento estiver hardcoded;
4. classes logísticas e perfis de transporte forem configuráveis;
5. elegibilidade considerar operação, zona, classe, rota e perfil;
6. FREE, FIXED_FEE, MANUAL_QUOTE e UNAVAILABLE puderem ser representados;
7. capacidade total, por classe, por janela e por dia for respeitada;
8. confirmação concorrente não produzir overbooking;
9. Bot registrar preferência sem prometer confirmação administrativa;
10. operador puder confirmar, reagendar, cancelar e concluir idempotentemente;
11. PICKUP e DELIVERY puderem ser solicitados pelos fluxos autorizados;
12. appointment puder existir antes da OS e ser associado depois;
13. uma OS puder possuir múltiplos movimentos logísticos;
14. a modelagem não impedir múltiplos equipamentos/OS por visita;
15. nenhum status logístico for acrescentado à OS MapOS;
16. MapOS não acessar diretamente o banco do Gateway;
17. cliente puder capturar e confirmar localização exata em página mobile HTTPS;
18. latitude, longitude, source, timestamp e accuracy forem validados/armazenados;
19. baixa precisão exigir recaptura ou fallback, não aceitação silenciosa;
20. ajuste de pin, WhatsApp Location e endereço/referência manual funcionarem
    conforme o escopo aprovado;
21. número, complemento e referência livre forem suportados e sanitizados;
22. tokens forem opacos, temporários, revogáveis, armazenados como hash e
    consumidos de forma idempotente na confirmação;
23. URL, logs, métricas e erros não expuserem PII, token ou coordenadas exatas;
24. endereço logístico não atualizar cadastro MapOS automaticamente;
25. PICKUP e DELIVERY puderem usar localizações distintas;
26. mudança relevante de localização exigir revalidação/reagendamento;
27. Human Lock suspender automação logística sem apagar dados coletados;
28. ação administrativa explícita continuar possível e auditada durante lock;
29. notificações forem resilientes, minimizadas e respeitarem latest-wins, com
    cancelamento prevalecendo sobre confirmação antiga;
30. operações, transições e eventos forem idempotentes e resistentes a ordem
    invertida/retry;
31. edge cases críticos possuírem testes unitários, integrados, de segurança e
    E2E em staging;
32. atualização futura do MapOS continuar sem acoplamento adicional indevido.

---

# 97. Meta de longo prazo

A arquitetura deve permitir:

```text
MapOS atual
        ↓
MapOS futuro
        ↓
outro ERP
```

sem precisar reescrever:

```text
Evolution
WhatsApp
Human Takeover
FSM
intake
logística
localização
templates
NLU
fila
logs
```

O `tecnina-bot` deve integrar o MapOS.

Ele não deve morar dentro do MapOS.

---

# 98. Resultado esperado para a operação da TecNina

Fluxo maduro:

```text
CLIENTE NOVO
    ↓
WhatsApp
    ↓
pré-atendimento
    ↓
draft
    ↓
se solicitar PICKUP
    ↓
zona + elegibilidade + localização + preferência
    ↓
operador confirma coleta
    ↓
equipamento coletado
    ↓
operador aprova o Intake
    ↓
MapOS cria cliente/OS
    ↓
os.created
    ↓
WhatsApp automático
    ↓
reparo
    ↓
status mudou
    ↓
WhatsApp automático
    ↓
cliente consulta detalhes no Portal
    ↓
operador assume conversa quando necessário
    ↓
Human Lock impede bot de interromper
    ↓
Finalizado
    ↓
WhatsApp automático
    ↓
retirada pelo cliente OU ação explícita de DELIVERY
    ↓
local/janela confirmados pelo operador
    ↓
entrega concluída
```

Objetivo final:

> reduzir trabalho repetitivo sem retirar do operador o controle das decisões importantes e sem transformar o fork do MapOS em uma base impossível de atualizar.

---

# 99. Instrução final para a IA responsável pela implementação

Não começar implementando o sistema inteiro.

A sequência obrigatória é:

```text
DISCOVERY
→ FUNDAMENTO
→ HUMAN TAKEOVER
→ EVENTOS
→ NOTIFICAÇÕES
→ PAINEL
→ HARDENING
→ INTAKE
→ APROVAÇÃO
→ LOGÍSTICA / LOCALIZAÇÃO / AGENDAMENTO
→ AUTOATENDIMENTO
→ AUTENTICAÇÃO
→ NLU
```

A **Fase 0 deve ser somente inspeção**.

Nenhuma suposição sobre a estrutura do MapOS deve substituir a leitura do código real.

Se existir conflito entre este documento e a implementação atual do MapOS:

> parar, documentar o conflito e propor a menor alteração possível antes de continuar.

A integração deve ser desenvolvida como um componente complementar ao MapOS, e não como uma reescrita dele.
