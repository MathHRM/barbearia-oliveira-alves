import { BrandMark } from '@/components/booking/brand-mark';
import { cn } from '@/lib/utils';
import { ChevronLeft } from 'lucide-react';
import type { ReactNode } from 'react';

export const STEPS = ['Serviço', 'Profissional', 'Horário', 'Dados', 'Pagamento', 'Pronto'] as const;

interface Props {
    step: number;
    title: string;
    subtitle?: string;
    onBack?: () => void;
    children: ReactNode;
    footer?: ReactNode;
}

export function WizardShell({ step, title, subtitle, onBack, children, footer }: Props) {
    return (
        <div className="bg-background text-foreground min-h-svh">
            <header className="border-border/80 bg-background/95 sticky top-0 z-10 border-b backdrop-blur">
                <div className="mx-auto flex w-full max-w-xl items-center gap-3 px-4 py-4">
                    <button
                        type="button"
                        onClick={onBack}
                        disabled={!onBack}
                        aria-label="Voltar"
                        className={cn(
                            'border-border flex size-9 shrink-0 items-center justify-center rounded-full border transition',
                            onBack ? 'text-foreground hover:border-primary hover:text-primary' : 'invisible',
                        )}
                    >
                        <ChevronLeft className="size-4" />
                    </button>

                    <BrandMark className="flex-1" />

                    <span className="tabular text-muted-foreground w-9 shrink-0 text-right text-xs">
                        {String(step).padStart(2, '0')}/{String(STEPS.length).padStart(2, '0')}
                    </span>
                </div>

                <div className="mx-auto flex w-full max-w-xl gap-1 px-4 pb-3">
                    {STEPS.map((label, index) => (
                        <span
                            key={label}
                            title={label}
                            className={cn('h-1 flex-1 rounded-full transition-colors', index < step ? 'bg-primary' : 'bg-border')}
                        />
                    ))}
                </div>
            </header>

            <main className="mx-auto w-full max-w-xl px-4 pt-6 pb-28">
                <p className="eyebrow">
                    Passo {String(step).padStart(2, '0')} · {STEPS[step - 1]}
                </p>
                <h1 className="mt-1 text-[1.75rem] leading-tight">{title}</h1>
                {subtitle && <p className="text-muted-foreground mt-2 text-sm">{subtitle}</p>}

                <div className="mt-6">{children}</div>
            </main>

            {footer && (
                <div className="border-border bg-background/95 fixed inset-x-0 bottom-0 border-t backdrop-blur">
                    <div className="mx-auto w-full max-w-xl px-4 py-3">{footer}</div>
                </div>
            )}
        </div>
    );
}
