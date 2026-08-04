import { cn } from '@/lib/utils';
import type { StatusTone } from '@/types/painel';

const TONES: Record<StatusTone, string> = {
    brand: 'border-primary/35 bg-primary/10 text-primary',
    success: 'border-success/35 bg-success/10 text-success',
    warning: 'border-brand-soft/35 bg-brand-soft/10 text-brand-soft',
    danger: 'border-destructive/35 bg-destructive/10 text-destructive',
};

/** Fundo 10% + borda 35% da cor semântica — o padrão de badge do design system. */
export function StatusBadge({ tone, children }: { tone: StatusTone; children: React.ReactNode }) {
    return <span className={cn('rounded-full border px-2.5 py-0.5 text-xs font-medium', TONES[tone])}>{children}</span>;
}
