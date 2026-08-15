# Barbearia Oliveira Alves — Spec do sistema

## Agendamento

O cliente escolhe serviço, profissional, horário, nome, WhatsApp, observação opcional e método de pagamento estimado. Os métodos aceitos são `pix`, `card` e `cash`. CPF e e-mail não fazem parte do formulário público.

O agendamento online é criado diretamente como `confirmed`, dentro de uma transação. A constraint `appointments_no_overlap` continua sendo a garantia de concorrência do horário. Preço e duração do catálogo são congelados no appointment; `price_cents` é referência, não faturamento recebido.

Agendamentos manuais também exigem `payment_method` e entram confirmados. O método é armazenado em `appointments.payment_method`, sem tabela de pagamentos.

Estados de serviço: `confirmed`, `attended`, `no_show`, `canceled` e `expired` (histórico legado não bloqueante). O cliente pode cancelar dentro da janela configurada; o painel pode cancelar com motivo. Nenhum cancelamento gera estorno ou chamada externa.

## Painel

O dashboard calcula, com base nos atendimentos `attended`, o valor estimado dos atendimentos, o valor médio por atendimento, volume, no-show e retenção. O gráfico é “Valor estimado por semana” e a lista mostra serviços mais realizados com valor estimado.

A agenda exibe o método analítico escolhido e o valor estimado quando aplicável. Não exibe “Recebido”, status de pagamento ou checkbox de estorno. Barbeiros continuam limitados à própria agenda e não veem os indicadores de valor.

## Dados e transição

Uma migração de transição adiciona `payment_method`, copia Pix/cartão/dinheiro dos registros antigos quando possível, converte reservas `pending_payment` para `confirmed`, remove `reserved_until`, atualiza a constraint de exclusividade e exclui `payments`, `webhook_events` e dados exclusivos do Asaas dos clientes. Agendamentos e clientes permanecem.

O Asaas não integra mais o sistema: não existem rotas de webhook, cobrança, polling, TTL de reserva, QR code, fatura ou refund.

## Rotas principais

```text
POST /agendamentos
GET  /agendamentos/{token}
POST /agendamentos/{token}/cancelar
GET  /agendamentos/{token}/agenda.ics
GET  /painel/agenda
POST /painel/agendamentos
POST /painel/agendamentos/{appointment}/cancelar
```

## Regras preservadas

- preço e duração são congelados no agendamento;
- antecedência mínima, horizonte, expediente e bloqueios continuam valendo;
- slots concorrentes retornam conflito;
- histórico, comparecimento, falta, retenção e churn permanecem;
- nenhum método de pagamento informado implica que o valor foi recebido.
