import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';

interface Props {
    selected?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
    className?: string;
}

/** Card largo clicável — a unidade de escolha de todos os passos do wizard. */
export function OptionCard({ selected = false, disabled = false, onClick, children, className }: Props) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-pressed={selected}
            className={cn(
                'bg-card w-full rounded-[1.125rem] border p-4 text-left transition',
                'focus-visible:ring-ring focus-visible:ring-2 focus-visible:outline-none',
                selected ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50',
                disabled && 'hover:border-border cursor-not-allowed opacity-40',
                className,
            )}
        >
            <div className="flex items-center gap-3">
                <div className="min-w-0 flex-1">{children}</div>
                <span
                    className={cn(
                        'flex size-5 shrink-0 items-center justify-center rounded-full border transition',
                        selected ? 'border-primary bg-primary text-primary-foreground' : 'border-border',
                    )}
                >
                    {selected && <Check className="size-3" strokeWidth={3} />}
                </span>
            </div>
        </button>
    );
}
