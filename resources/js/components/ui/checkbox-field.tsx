import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import { useId, type ReactNode } from 'react';

interface Props {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: ReactNode;
    description?: string;
    error?: string;
    disabled?: boolean;
    /** `card` desenha a linha inteira como alvo de clique — bom em painéis e diálogos */
    variant?: 'inline' | 'card';
    className?: string;
}

/** Caixa de marcação com rótulo clicável. Substitui `<input type="checkbox">` cru. */
export function CheckboxField({ checked, onChange, label, description, error, disabled, variant = 'inline', className }: Props) {
    const id = useId();

    return (
        <div className={className}>
            <label
                htmlFor={id}
                className={cn(
                    'flex cursor-pointer items-start gap-3 text-sm',
                    disabled && 'cursor-not-allowed opacity-60',
                    variant === 'card' && 'border-border bg-card hover:border-primary/40 rounded-xl border p-3 transition',
                    variant === 'card' && checked && 'border-primary/50 bg-primary/5',
                )}
            >
                <Checkbox
                    id={id}
                    checked={checked}
                    disabled={disabled}
                    onCheckedChange={(state) => onChange(state === true)}
                    className="mt-px size-[1.125rem]"
                />
                <span className="min-w-0">
                    <span className="block leading-snug">{label}</span>
                    {description && <span className="text-muted-foreground mt-0.5 block text-xs">{description}</span>}
                </span>
            </label>

            {error && <p className="text-destructive mt-1 text-xs">{error}</p>}
        </div>
    );
}
