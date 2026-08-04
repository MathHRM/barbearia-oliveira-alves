import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { CalendarDays, Clock } from 'lucide-react';
import { useRef, type ComponentProps } from 'react';

type NativeProps = Omit<ComponentProps<'input'>, 'type' | 'value' | 'onChange'>;

interface Props extends NativeProps {
    value: string;
    onChange: (value: string) => void;
    label?: string;
    hint?: string;
    error?: string;
    className?: string;
}

/** Dia — `<input type="date">` com números tabulares. Valor em `YYYY-MM-DD`. */
export function DateInput(props: Props) {
    return <PickerInput type="date" icon={CalendarDays} openLabel="Abrir calendário" {...props} />;
}

/** Hora — `<input type="time">` com números tabulares. Valor em `HH:mm`. */
export function TimeInput(props: Props) {
    return <PickerInput type="time" icon={Clock} openLabel="Abrir relógio" {...props} />;
}

function PickerInput({
    type,
    icon: Icon,
    openLabel,
    label,
    hint,
    error,
    className,
    value,
    onChange,
    disabled,
    ...props
}: Props & { type: 'date' | 'time'; icon: typeof Clock; openLabel: string }) {
    const input = useRef<HTMLInputElement>(null);

    // o indicador nativo fica escondido no CSS; o botão da marca abre o seletor
    const open = () => {
        input.current?.showPicker?.();
        input.current?.focus();
    };

    return (
        <Field label={label} hint={hint} error={error} className={className}>
            {(id) => (
                <div className="relative">
                    <button
                        type="button"
                        onClick={open}
                        disabled={disabled}
                        tabIndex={-1}
                        aria-label={openLabel}
                        className="text-muted-foreground hover:text-primary absolute top-1/2 left-3 -translate-y-1/2 transition disabled:pointer-events-none"
                    >
                        <Icon className="size-4" />
                    </button>

                    <Input
                        ref={input}
                        id={id}
                        type={type}
                        value={value}
                        disabled={disabled}
                        onChange={(event) => onChange(event.target.value)}
                        aria-invalid={error ? true : undefined}
                        className={cn('tabular picker-input h-10 pr-3 pl-9', error && 'border-destructive/60 focus-visible:ring-destructive/40')}
                        {...props}
                    />
                </div>
            )}
        </Field>
    );
}
