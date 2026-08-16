# Painel mobile-first
> Shell compartilhado, agenda operacional e dashboard analítico

Entry: `resources/js/layouts/painel-layout.tsx: PainelLayout()`

Shell: sidebar desktop → barra compacta mobile → menu recolhido com rotas filtradas por `auth.is_owner`.

Surfaces:
- `resources/js/pages/painel/agenda.tsx: Agenda()` — painel operacional do barbeiro; resumo aparece antes dos atendimentos em mobile.
- `resources/js/pages/painel/dashboard.tsx: Dashboard()` — métricas analíticas; rota protegida por `owner` em `routes/painel.php`.

Business rule: barbeiro não vê dashboard analítico nem valores; não alterar sem atualizar `SPEC.md` e `tests/Feature/Painel/DashboardTest.php`.

Updated: 2026-08-16
