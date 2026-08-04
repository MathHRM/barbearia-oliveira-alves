import { cn } from '@/lib/utils';

interface Props {
    label: string;
    value: string;
    hint?: string;
    delta?: number | null;
    className?: string;
}

/** Número solto com rótulo — usado em "Hoje em números" e nos KPIs do dashboard. */
export function StatCard({ label, value, hint, delta = null, className }: Props) {
    return (
        <div className={cn('border-border bg-card rounded-[1.125rem] border p-4', className)}>
            <p className="eyebrow">{label}</p>
            <p className="tabular mt-2 text-2xl font-semibold">{value}</p>
            {delta !== null && (
                <p className={cn('tabular mt-1 text-xs', delta >= 0 ? 'text-success' : 'text-destructive')}>
                    {delta >= 0 ? '+' : ''}
                    {delta}% vs. período anterior
                </p>
            )}
            {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
        </div>
    );
}
