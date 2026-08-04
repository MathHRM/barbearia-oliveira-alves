import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Loader2, Search, X } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

interface Props<T> {
    /** Rota que devolve JSON com a lista; o termo vai em `?q=`. */
    endpoint: string;
    label?: string;
    placeholder?: string;
    /** Abaixo de `minLength` nem consulta — evita varrer a base inteira a cada tecla. */
    minLength?: number;
    emptyMessage?: string;
    itemKey: (item: T) => string | number;
    renderItem: (item: T) => ReactNode;
    onPick: (item: T) => void;
    className?: string;
}

/** Campo de busca com resultado assíncrono: digita, espera o dedo parar, escolhe. */
export function AsyncSearch<T>({
    endpoint,
    label,
    placeholder = 'Buscar',
    minLength = 2,
    emptyMessage = 'Nada encontrado.',
    itemKey,
    renderItem,
    onPick,
    className,
}: Props<T>) {
    const [term, setTerm] = useState('');
    const [items, setItems] = useState<T[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (term.trim().length < minLength) {
            setItems([]);
            setLoading(false);

            return;
        }

        // a resposta antiga é descartada: quem responde por último pode ser um termo já apagado
        const controller = new AbortController();
        setLoading(true);

        const timer = window.setTimeout(() => {
            fetch(`${endpoint}?q=${encodeURIComponent(term.trim())}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : []))
                .then((data: T[]) => {
                    setItems(data);
                    setOpen(true);
                })
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 300);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [term, endpoint, minLength]);

    // clique fora fecha a lista sem mexer no que já foi escolhido
    useEffect(() => {
        const close = (event: MouseEvent) => {
            if (box.current && !box.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, []);

    const clear = () => {
        setTerm('');
        setItems([]);
        setOpen(false);
    };

    return (
        <div ref={box} className={cn('relative', className)}>
            <Field label={label}>
                {(id) => (
                    <div className="relative">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            id={id}
                            value={term}
                            autoComplete="off"
                            placeholder={placeholder}
                            onChange={(event) => setTerm(event.target.value)}
                            onFocus={() => items.length > 0 && setOpen(true)}
                            onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}
                            className="h-10 pr-9 pl-9"
                        />
                        {loading ? (
                            <Loader2 className="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin" />
                        ) : (
                            term !== '' && (
                                <button
                                    type="button"
                                    onClick={clear}
                                    aria-label="Limpar busca"
                                    className="text-muted-foreground hover:text-foreground absolute top-1/2 right-3 -translate-y-1/2"
                                >
                                    <X className="size-4" />
                                </button>
                            )
                        )}
                    </div>
                )}
            </Field>

            {open && !loading && term.trim().length >= minLength && (
                <ul className="border-border bg-popover absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border p-1 shadow-xl">
                    {items.length === 0 ? (
                        <li className="text-muted-foreground px-3 py-2 text-sm">{emptyMessage}</li>
                    ) : (
                        items.map((item) => (
                            <li key={itemKey(item)}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        onPick(item);
                                        clear();
                                    }}
                                    className="hover:bg-accent focus-visible:bg-accent w-full rounded-lg px-3 py-2 text-left text-sm outline-hidden"
                                >
                                    {renderItem(item)}
                                </button>
                            </li>
                        ))
                    )}
                </ul>
            )}
        </div>
    );
}
