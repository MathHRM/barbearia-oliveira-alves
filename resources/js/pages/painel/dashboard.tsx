import { BarChart } from '@/components/painel/bar-chart';
import { StatCard } from '@/components/painel/stat-card';
import { Button } from '@/components/ui/button';
import { PainelLayout } from '@/layouts/painel-layout';
import { brl, longDate } from '@/lib/format';
import { Head, router } from '@inertiajs/react';

interface Kpi {
    value: number;
    delta: number | null;
}

interface Props {
    range: string;
    ranges: string[];
    period: { from: string; to: string };
    kpis: {
        estimated_cents: Kpi;
        appointments: Kpi;
        average_cents: Kpi;
        churn_rate: Kpi;
        no_show_rate: Kpi;
    };
    weekly: { label: string; estimated_cents: number; appointments: number }[];
    retention: { recent_30: number; recent_60: number; lost: number; total: number };
    services: { name: string; appointments: number; estimated_cents: number }[];
    churn_days: number;
}

const RANGE_LABELS: Record<string, string> = { '30d': '30 dias', '90d': '90 dias', '12m': '12 meses' };

export default function Dashboard({ range, ranges, period, kpis, weekly, retention, services, churn_days }: Props) {
    return (
        <PainelLayout
            title="Dashboard"
            subtitle={`${longDate(period.from)} até ${longDate(period.to)}`}
            actions={
                <div className="flex w-full gap-1 overflow-x-auto pb-1 md:w-auto md:overflow-visible md:pb-0">
                    {ranges.map((option) => (
                        <Button
                            key={option}
                            size="sm"
                            className="min-h-10 shrink-0 md:min-h-9"
                            variant={option === range ? 'default' : 'ghost'}
                            onClick={() => router.get('/painel/dashboard', { range: option }, { preserveScroll: true })}
                        >
                            {RANGE_LABELS[option] ?? option}
                        </Button>
                    ))}
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="grid grid-cols-2 gap-2.5 sm:gap-3 xl:grid-cols-4">
                <StatCard
                    className="col-span-2 xl:col-span-1"
                    label="Valor estimado dos atendimentos"
                    value={brl(kpis.estimated_cents.value)}
                    delta={kpis.estimated_cents.delta}
                />
                <StatCard label="Agendamentos" value={String(kpis.appointments.value)} delta={kpis.appointments.delta} />
                <StatCard
                    className="col-span-2 xl:col-span-1"
                    label="Valor médio por atendimento"
                    value={brl(kpis.average_cents.value)}
                    delta={kpis.average_cents.delta}
                />
                <StatCard
                    label={`Churn ${churn_days} dias`}
                    value={`${kpis.churn_rate.value}%`}
                    delta={kpis.churn_rate.delta}
                    hint="Clientes com histórico que sumiram"
                />
            </div>

            <section className="border-border bg-card mt-5 rounded-[1.125rem] border p-4 sm:mt-6 sm:p-5">
                <div className="mb-5 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                    <p className="eyebrow">Valor estimado por semana</p>
                    <p className="text-muted-foreground text-xs">Últimas {weekly.length} semanas · só atendimentos concluídos</p>
                </div>
                <BarChart data={weekly} />
            </section>

            <div className="mt-5 grid gap-5 sm:mt-6 sm:gap-6 lg:grid-cols-2">
                <section className="border-border bg-card rounded-[1.125rem] border p-4 sm:p-5">
                    <p className="eyebrow">Retenção</p>
                    <ul className="mt-4 space-y-3">
                        <RetentionRow label="Voltaram nos últimos 30 dias" value={retention.recent_30} total={retention.total} tone="bg-success" />
                        <RetentionRow label="Voltaram entre 30 e 60 dias" value={retention.recent_60} total={retention.total} tone="bg-primary" />
                        <RetentionRow label={`Sumiram (${churn_days}+ dias)`} value={retention.lost} total={retention.total} tone="bg-destructive" />
                    </ul>
                    <p className="text-muted-foreground mt-4 text-xs">
                        Base: {retention.total} cliente{retention.total === 1 ? '' : 's'} com pelo menos uma visita.
                    </p>
                </section>

                <section className="border-border bg-card rounded-[1.125rem] border p-4 sm:p-5">
                    <div className="flex items-baseline justify-between gap-3">
                        <p className="eyebrow">Serviços mais realizados</p>
                        <p className="tabular text-muted-foreground text-xs">No-show {kpis.no_show_rate.value}%</p>
                    </div>

                    {services.length === 0 ? (
                        <p className="text-muted-foreground mt-4 text-sm">Nenhum atendimento concluído no período.</p>
                    ) : (
                        <ul className="mt-4 space-y-3">
                            {services.map((service) => (
                                <li key={service.name} className="flex items-center justify-between gap-3 text-sm">
                                    <span className="min-w-0 truncate">{service.name}</span>
                                    <span className="text-muted-foreground tabular shrink-0 text-xs">
                                        {service.appointments}x · <span className="text-foreground">{brl(service.estimated_cents)}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </PainelLayout>
    );
}

function RetentionRow({ label, value, total, tone }: { label: string; value: number; total: number; tone: string }) {
    const percent = total === 0 ? 0 : Math.round((value / total) * 100);

    return (
        <li className="space-y-1.5">
            <div className="flex items-baseline justify-between text-sm">
                <span>{label}</span>
                <span className="tabular text-muted-foreground text-xs">
                    {value} · {percent}%
                </span>
            </div>
            <div className="bg-accent h-1.5 w-full overflow-hidden rounded-full">
                <div className={`${tone} h-full rounded-full`} style={{ width: `${percent}%` }} />
            </div>
        </li>
    );
}
