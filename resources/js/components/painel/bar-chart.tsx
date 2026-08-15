import { brl } from '@/lib/format';

interface Bar {
    label: string;
    estimated_cents: number;
    appointments: number;
}

/** Barras em CSS puro — nenhuma lib de gráfico para 12 colunas. */
export function BarChart({ data }: { data: Bar[] }) {
    const peak = Math.max(...data.map((bar) => bar.estimated_cents), 1);

    return (
        <div className="flex h-52 items-end gap-2">
            {data.map((bar) => (
                <div key={bar.label} className="group flex min-w-0 flex-1 flex-col items-center gap-2">
                    <div className="relative flex w-full flex-1 items-end">
                        <div
                            className="bg-primary/70 group-hover:bg-primary w-full rounded-t-md transition-all"
                            style={{ height: `${Math.max((bar.estimated_cents / peak) * 100, 2)}%` }}
                        />
                        <span className="bg-popover border-border tabular pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 rounded-md border px-2 py-1 text-xs whitespace-nowrap group-hover:block">
                            {brl(bar.estimated_cents)} · {bar.appointments}x
                        </span>
                    </div>
                    <span className="tabular text-muted-foreground truncate text-[0.625rem]">{bar.label}</span>
                </div>
            ))}
        </div>
    );
}
