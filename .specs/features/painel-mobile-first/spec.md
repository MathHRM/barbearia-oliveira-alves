# Painel mobile-first

## Contexto

O painel atual usa navegação horizontal em telas pequenas e concentra a agenda em
cards que precisam competir com muitas ações na mesma linha. O barbeiro usa a
agenda como sua superfície operacional principal; o dashboard analítico continua
restrito ao dono conforme `SPEC.md`.

## Objetivo

Tornar o painel confortável para uso recorrente em celular, reduzindo a carga
visual do menu, priorizando a agenda do dia e garantindo que cards e ações sejam
tocáveis sem alterar regras de negócio ou permissões.

## Requisitos

### PM-001 — Menu mobile contraído

Em viewport pequena, a navegação deve aparecer recolhida em uma barra compacta e
ser aberta por um controle identificável. O menu aberto deve mostrar somente as
rotas disponíveis para o usuário atual, indicar a rota ativa e permitir fechar
após a navegação.

### PM-002 — Agenda como painel operacional

A agenda deve priorizar a data e a próxima ação do barbeiro: navegação de dia,
lançamento de balcão, lista de atendimentos e resumo do dia devem permanecer
compreensíveis sem zoom ou rolagem horizontal.

### PM-003 — Cards de atendimento e resumo

Em telas pequenas, cada atendimento deve empilhar horário, cliente, serviço e
ações; os botões de presença/cancelamento devem ter área de toque adequada e não
serem comprimidos pela informação secundária. O resumo do dia deve aparecer
antes do formulário de bloqueio.

### PM-004 — Dashboard analítico responsivo

Quando acessível ao dono, os filtros de período, KPIs, gráfico e listas devem
fluir em uma coluna legível no celular, sem alterar os dados, rótulos, cálculos ou
o controle de acesso existente.

### PM-005 — Acessibilidade e estabilidade

Controles novos devem possuir nome acessível, foco visível, fechamento por Escape
quando aplicável e respeitar `prefers-reduced-motion`. Não deve haver overflow
horizontal introduzido pelo painel.

## Fora de escopo

- Liberar indicadores financeiros para barbeiros.
- Alterar endpoints, métricas, status ou regras de autorização.
- Redesenhar o fluxo público de agendamento.

## Critérios de aceite

- [ ] PM-001: menu mobile fechado inicialmente, abre/fecha com botão e mantém a
  rota ativa.
- [ ] PM-002: agenda em 375px não exige rolagem horizontal para consultar o dia
  ou lançar uma ação.
- [ ] PM-003: ações de um atendimento não se sobrepõem nem ficam menores que o
  padrão de toque do componente.
- [ ] PM-004: dashboard analítico mantém seus props e testes atuais, com filtros
  e cards utilizáveis em 375px.
- [ ] PM-005: build, lint, formatação e testes de painel passam; validação de
  navegador será declarada separadamente se não estiver disponível.
