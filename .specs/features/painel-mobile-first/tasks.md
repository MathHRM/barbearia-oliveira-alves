# Tasks — painel mobile-first

- [x] T1 — Menu recolhido no shell compartilhado
  - Onde: `resources/js/layouts/painel-layout.tsx`
  - Feito quando: mobile usa botão com `aria-expanded`, rota ativa e fechamento;
    desktop permanece com sidebar.
  - Verificação: `npm run format:check`, build e inspeção de classes responsive.

- [x] T2 — Agenda vertical e orientada a toque
  - Onde: `resources/js/pages/painel/agenda.tsx`
  - Feito quando: toolbar, resumo, atendimento e ações cabem em 375px sem
    compressão/overflow.
  - Verificação: testes de agenda, build e smoke visual mobile.

- [x] T3 — Dashboard analítico responsivo
  - Onde: `resources/js/pages/painel/dashboard.tsx` e, se necessário,
    `resources/js/components/painel/stat-card.tsx`.
  - Feito quando: período, KPIs e seções são legíveis em 375px sem alterar
    contrato ou autorização.
  - Verificação: `DashboardTest`, build, lint e formatação.

- [x] T4 — Gate final
  - Feito quando: diff limpo de problemas, testes executados e lacunas visuais
    explicitadas.
  - Verificação: `docker compose exec -T vite npm run format:check`,
    `docker compose exec -T vite npm run build`, lint e testes Laravel.
