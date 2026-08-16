import { cn } from '@/lib/utils';
import type { StatusTone } from '@/types/painel';

const TONES: Record<StatusTone, string> = {
    brand: 'border-primary/35 bg-primary/10 text-primary',
    success: 'border-success/35 bg-success/10 text-success',
    warning: 'border-amber-500/35 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    danger: 'border-destructive/35 bg-destructive/10 text-destructive',
};

/** Fundo 10% + borda 35% da cor semântica — o padrão de badge do design system. */
export function StatusBadge({ tone, children }: { tone: StatusTone; children: React.ReactNode }) {
    return <span className={cn('rounded-full border px-2.5 py-0.5 text-xs font-medium', TONES[tone])}>{children}</span>;
}
