# Barbearia Oliveira Alves — Spec do sistema

Fonte de verdade visual: projeto Claude Design `8ff16569-9105-4977-b93f-ba2e2faa3a64`,
arquivo `Barbearia Oliveira Alves.dc.html`.

---

## 1. Stack e deploy

| Camada | Escolha |
|---|---|
| Backend | Laravel 12 (PHP 8.3) |
| Front | Inertia.js + React 19 + TypeScript + shadcn/ui + Tailwind v4 |
| Banco | PostgreSQL 16 |
| Fila | `database` driver + worker |
| Gateway | **Asaas** (Pix + cartão) |
| E-mail | SMTP/Resend via `Laravel Mail` (queued) |
| Host | Render — 1 Web Service (monolito), 1 Background Worker (queue), 1 Cron Job, 1 Postgres |

Monolito Inertia: uma deploy, sem CORS, sessão do Laravel resolve auth do admin.

### Serviços Render
- `web` — `php artisan serve`/nginx+fpm, build `npm run build && php artisan migrate --force`
- `worker` — `php artisan queue:work --tries=3`
- `cron` — a cada 5 min: `php artisan appointments:expire` (varre reservas vencidas)
- `cron` — diário 08:00: `php artisan appointments:remind` (lembrete D-1)

⚠️ Inferido — não documentado: nomes de serviço/plano do Render, domínio e DNS.

---

## 2. Papéis

| Papel | Pode |
|---|---|
| `owner` | Tudo: agenda de todos, dashboard financeiro, serviços, horários, barbeiros, clientes |
| `barber` | Só a própria agenda; marcar compareceu/faltou/cancelar; bloquear o próprio horário |

Cliente **não tem login**. Identificado por telefone normalizado (E.164).
Gestão do próprio agendamento por link com token assinado (`signed URL`, validade até o horário).

---

## 3. Modelo de dados

```
users            id, name, email, password, role(owner|barber), active, timestamps
barbers          id, user_id FK, display_name, headline, initials, sort_order, active
services         id, name, description, duration_min, price_cents, active, sort_order
working_hours    id, barber_id FK, weekday 0-6, starts_at time, ends_at time
                 UNIQUE(barber_id, weekday, starts_at)
time_blocks      id, barber_id FK, starts_at timestamptz, ends_at timestamptz, reason
customers        id, name, phone_e164 UNIQUE, email, notes, first_seen_at, last_visit_at
appointments     id, customer_id FK, barber_id FK, service_id FK,
                 starts_at timestamptz, ends_at timestamptz,
                 status (enum), origin (online|manual),
                 price_cents,            -- congelado no momento do agendamento
                 duration_min,           -- congelado
                 customer_note,
                 reserved_until timestamptz NULL,   -- TTL da reserva
                 confirmed_at, attended_at, canceled_at, cancel_reason,
                 public_token UUID
payments         id, appointment_id FK, provider ('asaas'), provider_payment_id,
                 billing_type (PIX|CREDIT_CARD), amount_cents, status,
                 invoice_url, pix_payload, paid_at, refunded_at, refund_amount_cents, raw jsonb
webhook_events   id, provider, external_id UNIQUE, event, payload jsonb, processed_at
```

### Constraint de concorrência (crítica)
```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE appointments ADD CONSTRAINT appointments_no_overlap
EXCLUDE USING gist (
  barber_id WITH =,
  tstzrange(starts_at, ends_at) WITH &&
) WHERE (status IN ('pending_payment','confirmed','attended'));
```
Duas pessoas clicando no mesmo slot: a segunda transação estoura, API responde
`409 slot_taken` e o front recarrega os horários. Sem lock aplicativo, sem race.

---

## 4. Estados do agendamento

```
pending_payment ──(webhook aprovado)──> confirmed ──> attended
      │                                     │
      │ (TTL 10min / recusado)              ├──> no_show
      ▼                                     │
   expired                                  └──> canceled
```

| Status | Ocupa slot | Origem |
|---|---|---|
| `pending_payment` | sim (até `reserved_until`) | online |
| `confirmed` | sim | online (webhook) / manual (direto) |
| `attended` | sim | ação do barbeiro |
| `no_show` | não (histórico) | ação do barbeiro |
| `canceled` | não | cliente ou barbeiro |
| `expired` | não | job/lazy |

Agendamento `manual` (balcão/telefone) entra direto em `confirmed`, `payment` nulo,
valor contabilizado como recebido no local.

---

## 5. Cálculo de disponibilidade

Entrada: `service_id`, `barber_id | any`, janela de datas.

1. Expande `working_hours` do(s) barbeiro(s) no período → intervalos abertos.
2. Subtrai `time_blocks`.
3. Subtrai `appointments` em `pending_payment|confirmed|attended`.
4. Fatia o restante em passos de **15 min**, mantendo só os inícios onde cabe
   `service.duration_min` inteiro dentro de um intervalo aberto.
5. Descarta slots com início < `now + 60 min` (antecedência mínima).
6. Horizonte: **21 dias** à frente.

Timezone fixo `America/Sao_Paulo`; persistência em UTC (`timestamptz`).
`barber = any` → une os slots, e na confirmação atribui o barbeiro com menos
agendamentos no dia (desempate: menor `sort_order`).

Slots são calculados on-the-fly. **Não há tabela de slots.**

---

## 6. Fluxo público (mobile-first, 6 telas)

`01 Serviço → 02 Profissional → 03 Horário → 04 Dados → 05 Pagamento → 06 Confirmação`

- **01** Lista de serviços (nome, descrição, duração, preço).
- **02** Barbeiros + opção "Tanto faz · quem estiver livre primeiro".
- **03** Régua de dias (com contagem de horários livres) + horários do dia escolhido.
  Implementado na variante **A: cards largos** — um card por horário, mostrando o
  barbeiro quando o cliente escolheu "tanto faz".
- **04** Nome, WhatsApp, **CPF**, e-mail, observação opcional. `upsert` em `customers` por telefone.
  O CPF é obrigatório **também no Pix**: toda cobrança do Asaas exige um `customer`, e
  `POST /v3/customers` exige `cpfCnpj`. Não há caminho de Pix sem CPF.
- **05** Resumo + Pix (QR + copia-e-cola, confirmação em segundos) ou cartão (até 2x).
  Juros/taxa do parcelamento ficam com **o estabelecimento**: a cobrança é criada com o
  valor cheio do serviço, sem `installmentValue` de repasse, e o cliente parcela na fatura
  hospedada pagando o mesmo total.
- **06** Confirmação com código `#OA-XXXX`, botão de calendário (.ics) e endereço.

### Sequência de pagamento
```
POST /agendamentos
  ├─ valida slot ainda livre
  ├─ transação: cria customer (upsert) + appointment
  │    status=pending_payment, reserved_until = now()+10min
  │    (constraint EXCLUDE garante exclusividade)
  ├─ Asaas: cria customer + payment (dueDate hoje, value = price)
  └─ devolve invoiceUrl / pix payload
       ↓ cliente paga
Asaas → POST /webhooks/asaas  (valida asaas-access-token)
  ├─ grava webhook_events (external_id UNIQUE = idempotência)
  ├─ PAYMENT_CONFIRMED | PAYMENT_RECEIVED → appointment.confirmed
  │    reserved_until = null, confirmed_at = now, dispara e-mail + .ics
  └─ PAYMENT_REFUNDED → canceled ; PAYMENT_OVERDUE → expired
```
Fallback: a tela de pagamento faz polling em `GET /agendamentos/{token}/status`
a cada 3s (webhook pode atrasar). Reserva expirada → job `appointments:expire`
marca `expired` e o slot volta à lista.

### Cancelamento e reembolso
- Cliente cancela sozinho, pelo link assinado, **até 12h antes**: `POST /asaas/payments/{id}/refund` valor cheio, status `canceled`, slot liberado, e-mail de confirmação.
- Menos de 12h: botão desabilitado, texto orienta contato por WhatsApp. Só o barbeiro cancela, e decide o estorno manualmente no painel.
- Regra exibida no passo 05 antes do pagamento.

---

## 7. Painel administrativo

Login e-mail+senha (`/painel/login`). Sem cadastro público. Tema **dark**, igual ao público.

### Agenda do dia (tela padrão)
Linha por agendamento: hora · cliente (+ meta: nº de visitas, forma de pagamento) ·
serviço · badge de status · ações `Compareceu` / `Cancelar`.
Navegação ‹ Ontem / Amanhã ›. Cabeçalho com contagem, receita prevista e horários livres.
Lateral: "Hoje em números" (confirmados, compareceram, cancelados, recebido) e
"Bloquear horário" (cria `time_block`; some da agenda pública na hora).
`barber` vê só as próprias linhas; `owner` vê todas com filtro por profissional.

### Dashboard
KPIs: Faturamento no mês · Agendamentos · Ticket médio · Churn 60 dias — cada um com delta vs. mês anterior.
Gráfico de barras: faturamento por semana, últimas 12 semanas.
Painel de retenção: voltaram em 30d / voltaram em 60d / sumiram (60+ dias).

**Definições**
- *Faturamento* = soma de `price_cents` de `attended` no período (online + balcão).
- *Ticket médio* = faturamento ÷ nº de `attended`.
- *Churn 60d* = clientes com ≥1 visita cujo `last_visit_at` < hoje−60d, sobre o total de clientes com histórico.
- *No-show* = `no_show` ÷ (`attended` + `no_show`).

### Serviços e horários
CRUD de serviços (nome, descrição, duração, preço, ativo) e grade semanal de funcionamento.
Alterar preço **não** altera agendamentos já criados (valor congelado em `appointments.price_cents`).

### Clientes
Busca por nome/telefone. Colunas: cliente, WhatsApp, último corte, visitas, situação
(`Novo` / `Ativo` / `Fiel` / `Perdido`). Perdido = 60+ dias sem voltar.

---

## 8. Notificações (e-mail, enfileiradas)

| Gatilho | Conteúdo |
|---|---|
| Pagamento confirmado | Comprovante + data/hora + profissional + link de gestão + `.ics` |
| D-1 às 08:00 | Lembrete com horário e link |
| Cancelamento | Aviso + status do estorno |

WhatsApp fica para v2 (custo de número + templates).

---

## 9. Design system

Extraído da logo: preto, ciano como único destaque, títulos geométricos pesados.

| Token | Hex |
|---|---|
| Preto base | `#0B0C0E` |
| Superfície | `#111417` |
| Borda | `#1E2226` |
| Ciano marca | `#22C7DC` |
| Ciano claro | `#5FE0EF` |
| Texto | `#F2F4F5` |
| Texto suave | `#8B9298` |
| Alerta | `#E05A4E` |
| Sucesso | `#8FD86A` |

**Tipografia** — Poppins 700 (títulos 28–34px, `-0.02em`), Poppins 600 (subtítulos e botões
15–20px), IBM Plex Sans 400/500 (corpo 13–15px, `1.55`), IBM Plex Mono (horas, telefones, códigos).

**Raio** — 0.875rem base; botões 13px; cards 14–18px; pills 999px.

```css
--background: 210 9% 5%;
--card: 200 8% 8%;
--border: 205 9% 14%;
--foreground: 200 8% 95%;
--muted-foreground: 202 6% 58%;
--primary: 187 73% 50%;
--primary-foreground: 200 20% 6%;
--destructive: 4 70% 57%;
--radius: 0.875rem;
```

**Badges de status** — fundo 10% + borda 32–35% da cor: confirmado (ciano),
compareceu (verde `#8FD86A`), cancelado (vermelho `#E05A4E`).

### Componentes (`resources/js/components/ui`)

| Componente | Uso |
|---|---|
| `Field` | rótulo + controle + dica/erro; base dos campos. `children` recebe o `id` gerado |
| `DateInput` | dia (`YYYY-MM-DD`), números tabulares, botão de calendário abre o seletor nativo |
| `TimeInput` | hora (`HH:mm`), mesma anatomia com ícone de relógio |
| `CheckboxField` | caixa + rótulo clicável; `variant="card"` desenha a linha inteira como alvo |
| `Pagination` | rodapé de tabela: "Mostrando 1–15 de 42 clientes" + páginas, tudo em pt-BR |

O ícone nativo do Chrome em `date`/`time` é escondido pela classe `.picker-input`
(`resources/css/app.css`); quem abre o seletor é o botão da marca, via `showPicker()`.

---

### Fuso horário

`APP_TIMEZONE=UTC`; o fuso da barbearia vive em `BARBEARIA_TZ` e vale para grade,
agenda e exibição. Colunas `timestamptz` usam o cast `App\Casts\UtcDateTime`.

---

## 10. Rotas

### Públicas
```
GET  /                              wizard (Inertia)
GET  /api/services
GET  /api/barbers
GET  /api/availability              ?service_id&barber_id|any&from&to
POST /agendamentos                  cria reserva + cobrança Asaas
GET  /agendamentos/{token}          detalhe (link assinado)
GET  /agendamentos/{token}/status   polling
POST /agendamentos/{token}/cancelar
POST /webhooks/asaas
```

### Painel (`auth`, prefixo `/painel`)
```
GET  /painel/login                  mesma tela de /login · POST /login · POST /logout
GET  /painel/agenda                 ?date=&barber_id=
POST /painel/agendamentos           criação manual (balcão)
POST /painel/agendamentos/{id}/compareceu | /faltou | /cancelar
POST /painel/bloqueios   ·   DELETE /painel/bloqueios/{id}
GET  /painel/clientes               ?q=  (busca por nome ou telefone)
GET  /painel/horarios   ·   PUT /painel/horarios/{barber}   grade semanal
GET  /painel/dashboard              ?range=30d|90d|12m       (owner)
GET|POST /painel/servicos   ·   PUT|DELETE /painel/servicos/{id}   (owner)
GET|POST /painel/barbeiros  ·   PUT /painel/barbeiros/{id}         (owner)
```

Sem cadastro público: o dono cria os barbeiros e a senha pelo painel. Usuário
com `active = false` não passa do login.

---

## 11. Regras de negócio (resumo executável)

1. Slot só é reservado dentro de transação, com `EXCLUDE` garantindo exclusividade.
2. Reserva sem pagamento expira em **10 min** e devolve o slot.
3. Nenhum agendamento online vira `confirmed` sem webhook aprovado do Asaas.
4. Webhook é idempotente por `external_id`.
5. Preço e duração são congelados no agendamento.
6. Antecedência mínima de 60 min; horizonte de 21 dias.
7. Cancelamento pelo cliente só até 12h antes, com estorno integral automático.
8. `barber` nunca enxerga faturamento nem agenda alheia.
9. Todo horário exibido ao cliente respeita `working_hours` − `time_blocks` − ocupados.

---

## Questões em aberto

1. ~~Variante de horários~~ — decidido: A (cards largos).
2. ~~Cartão em 2x — quem paga os juros/taxa?~~ — decidido: o estabelecimento absorve.
3. ~~Antecedência mínima 60 min~~ — confirmado, fica em 60 min (`BARBEARIA_MIN_LEAD_MIN`).
4. ~~Conta Asaas e chave de API~~ — chave entra por env (`ASAAS_API_KEY`); provisionamento é do dono.
5. Domínio final e remetente de e-mail (SPF/DKIM) — ainda não definido; bloqueia o envio em produção.
6. ~~Endereço real da barbearia~~ — Av. Márcia Antônia, 1052 · Tupanuara · São Joaquim de Bicas/MG.
7. ~~"Tanto faz" sem saber o barbeiro antes de pagar~~ — aceito.
