import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useId, type ReactNode } from 'react';

interface Props {
    label?: string;
    hint?: string;
    error?: string;
    className?: string;
    /** recebe o id gerado para amarrar rótulo e controle */
    children: (id: string) => ReactNode;
}

/** Rótulo + controle + dica/erro. Base dos campos do design system. */
export function Field({ label, hint, error, className, children }: Props) {
    const id = useId();

    return (
        <div className={cn('space-y-1.5', className)}>
            {label && (
                <Label htmlFor={id} className="text-muted-foreground text-xs">
                    {label}
                </Label>
            )}

            {children(id)}

            {error ? <p className="text-destructive text-xs">{error}</p> : hint && <p className="text-muted-foreground text-xs">{hint}</p>}
        </div>
    );
}
