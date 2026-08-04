import { cn } from '@/lib/utils';

/** Lockup da logo: "barbearia" em ciano manuscrito sobre "OLIVEIRA ALVES" em caixa alta. */
export function BrandMark({ className }: { className?: string }) {
    return (
        <div className={cn('flex flex-col items-center leading-none', className)}>
            <span className="font-display text-primary text-lg font-semibold tracking-tight lowercase">barbearia</span>
            <span className="font-display text-foreground text-xl font-bold tracking-[0.18em] uppercase">Oliveira Alves</span>
        </div>
    );
}
