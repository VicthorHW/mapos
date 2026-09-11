# Ajustes Pendentes V3 — Frontend MapOS

Este documento detalha **exatamente o que falta ser implementado no frontend do MapOS** (interface administrativa e formulário público) para concluir a integração com as especificações da V3.

> **Nota:** Toda a lógica de backend (API, banco de dados, fluxo do WhatsApp, controle de estados, persistência de GPS e formatações de string) **já foi 100% implementada e testada no repositório do Gateway (tecnina-bot)**. O MapOS precisará apenas construir a interface visual que consome as APIs já prontas.

---

## 1. Localização e GPS (Painel de Pré-atendimentos)

O Gateway já extrai e salva corretamente a latitude e a longitude vindas pelo WhatsApp (seja via envio estático nativo ou via link do browser).

**O que fazer no MapOS:**
Na tela de detalhe de um pré-atendimento (Intake), quando o cliente tiver enviado o GPS, a interface deve ler os dados e renderizar as seguintes informações:
1. Um indicativo da origem: `GPS recebido pelo WhatsApp` (ou pelo navegador).
2. Um link clicável para abrir no mapa. O link deve ser construído de forma nativa e sem uso de APIs pagas, seguindo a estrutura exata: `https://www.google.com/maps?q={latitude},{longitude}`

*Regra de negócio:* O frontend não precisa fazer geocodificação nem converter endereços. Apenas apresentar o que o backend já processou.

---

## 2. Gate de Taxa de Coleta (Painel de Pré-atendimentos)

A regra de taxa já possui seus próprios estados consolidados (`PENDING_TEAM`, `PENDING_CUSTOMER`, `ACCEPTED`, etc.).

**O que fazer no MapOS:**
1. Quando o pré-atendimento cair como coleta sob orçamento manual, o status administrativo deve indicar claramente a ação esperada: **Aguardando envio da taxa**.
2. A interface deve disponibilizar o campo de valor e o botão **"Enviar taxa"**.
3. O frontend deve disparar a requisição para a rota administrativa do Gateway (já ajustada para prevenir falhas de serialização e estados pendentes).
4. Em caso de sucesso, o status exibido para a equipe deve mudar automaticamente para **Aguardando confirmação da taxa**.

*Regra de negócio:* Não duplicar validações lógicas complexas no frontend. O botão deve simplesmente chamar a API `/api/admin/intakes/{id}/pickup-fee` passando o valor. A API garante a idempotência.

---

## 3. Agenda de Entrega Presencial (Drop-off)

O modelo de dados (`DropoffScheduleDay`, `DropoffSchedulePeriod`) e a API de horários de funcionamento já existem no backend. O FSM do bot já calcula e avisa o cliente sobre os "próximos 4 dias úteis" dinamicamente baseado na API.

**O que fazer no MapOS:**
1. Criar uma nova tela nas Configurações (ex: "Agenda de Entregas") voltada para os horários de recebimento presencial da TecNina.
2. Construir o formulário/CRUD que alimente essa API, exigindo:
   - Controle dos sete dias da semana (ativar/desativar cada dia).
   - Múltiplos períodos por dia (ex: Manhã das 09h às 12h, Tarde das 13h30 às 18h).
3. Salvar os dados na API administrativa correspondente do Gateway.

*Regra de negócio:* Esta interface **não é para agendamento do cliente**, é apenas para informar o "Horário de Funcionamento Logístico". O frontend deve apenas ser a tela de preenchimento para alimentar a API.

---

## 4. Formulário Web Público (`/p/{token}`)

A FSM do WhatsApp já isola completamente os fluxos logísticos (`FLOW-04` e `FLOW-11`).

**O que fazer no MapOS:**
1. Garantir que, quando o usuário acessa o link do formulário (`/p/`) e escolhe "Levar o equipamento até a TecNina" (Entrega Presencial), **o formulário não solicite cidade, CEP, logradouro e GPS**.
2. Quando ele escolhe "Buscar em casa" (Coleta), aí sim os campos logísticos e de validação de endereço devem ser exibidos e preenchidos.
3. O formulário deve respeitar o mesmo "gate formal de taxa" do WhatsApp, ou seja: apenas enviar os dados confirmados do formulário para o backend, deixando o status de aceite/confirmação a cargo da mecânica global do Intake.

*Regra de negócio:* O frontend público deve espelhar a simplicidade do FSM, sem exigir dados extras. O backend Gateway é a fonte de verdade final na recepção desse POST.

---

## Próximos Passos (Operacional)

Após os desenvolvedores implementarem e mesclarem as telas acima no repositório do MapOS:
1. Coordenar o deploy do backend (Gateway no Coolify) com a migração do banco (`alembic upgrade head`) juntamente com as atualizações do frontend (MapOS).
