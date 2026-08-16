# Design — painel mobile-first

## Direção

Modern utility já estabelecido no produto: fundo quase preto, superfícies
levemente elevadas, tipografia IBM Plex Sans/Poppins e ciano como sinal de ação.
A assinatura da revisão é uma navegação móvel recolhida e uma pequena linha
ciano no estado ativo; o restante permanece disciplinado para favorecer leitura
durante o trabalho no balcão.

## Layout

Desktop mantém sidebar fixa e conteúdo amplo. Mobile usa barra superior compacta
com marca, título e botão de menu; o menu aberto ocupa apenas a largura necessária
e fecha ao clicar em uma rota. A agenda vira uma sequência vertical:

```text
[marca] [título curto] [menu]
[< dia] [data] [dia >]       [Balcão]
[agendados] [compareceram]
[livres]    [cancelados]
[atendimento empilhado]
[bloquear horário]
```

No dashboard analítico, os filtros ficam em rolagem horizontal contida e os KPIs
viram uma grade de duas colunas com valores curtos; se o rótulo exigir, o card
ocupa a largura completa.

## Implementação

- `painel-layout.tsx`: estado local do menu e drawer/popover móvel; sidebar
  desktop continua sendo a mesma fonte de itens.
- `agenda.tsx`: classes responsivas na toolbar, resumo antes do painel lateral em
  mobile e `Row` com layout vertical/ações fluidas.
- `dashboard.tsx`: toolbar de período em linha controlada e espaçamentos/cards
  responsivos.
- `stat-card.tsx`: permitir que o card ocupe a largura disponível sem alterar
  seu contrato de dados.

## Decisões preservadas

- Sem mudança em autorização, props Inertia ou serviços de métricas.
- Sem dependência nova; usar React, Tailwind e ícones já instalados.
- Controles continuam com `Button`, `Link` e primitives existentes.
