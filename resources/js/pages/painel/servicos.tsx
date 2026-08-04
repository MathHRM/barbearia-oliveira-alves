import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PainelLayout } from '@/layouts/painel-layout';
import { brl, duration } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface ServiceRow {
    id: number;
    name: string;
    description: string | null;
    duration_min: number;
    price_cents: number;
    active: boolean;
    appointments_count: number;
}

export default function Servicos({ services }: { services: ServiceRow[] }) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);

    return (
        <PainelLayout
            title="Serviços"
            subtitle="Duração e preço valem para novos agendamentos; o que já foi marcado mantém o valor."
            actions={
                <Button size="sm" onClick={() => setEditing('new')}>
                    <Plus className="size-4" /> Novo serviço
                </Button>
            }
        >
            <Head title="Serviços" />

            <div className="space-y-3">
                {editing === 'new' && <ServiceForm onClose={() => setEditing(null)} />}

                {services.map((service) =>
                    editing === service.id ? (
                        <ServiceForm key={service.id} service={service} onClose={() => setEditing(null)} />
                    ) : (
                        <article
                            key={service.id}
                            className="border-border bg-card flex flex-wrap items-center gap-4 rounded-[1.125rem] border p-4"
                            onDoubleClick={() => setEditing(service.id)}
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="font-display truncate text-base font-semibold">{service.name}</p>
                                    {!service.active && <span className="text-muted-foreground text-xs">inativo</span>}
                                </div>
                                <p className="text-muted-foreground mt-1 truncate text-sm">{service.description ?? '—'}</p>
                            </div>

                            <p className="tabular text-muted-foreground text-sm">{duration(service.duration_min)}</p>
                            <p className="tabular text-primary w-24 text-right font-semibold">{brl(service.price_cents)}</p>

                            <div className="flex gap-2">
                                <Button size="sm" variant="outline" onClick={() => setEditing(service.id)}>
                                    Editar
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="text-destructive"
                                    onClick={() => router.delete(`/painel/servicos/${service.id}`, { preserveScroll: true })}
                                    aria-label="Remover serviço"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </article>
                    ),
                )}
            </div>
        </PainelLayout>
    );
}

function ServiceForm({ service, onClose }: { service?: ServiceRow; onClose: () => void }) {
    const { data, setData, post, put, transform, processing, errors } = useForm({
        name: service?.name ?? '',
        description: service?.description ?? '',
        duration_min: service?.duration_min ?? 30,
        // o formulário fala em reais; o banco guarda centavos
        price: ((service?.price_cents ?? 0) / 100).toFixed(2),
        active: service?.active ?? true,
    });

    // o erro volta na chave do servidor (`price_cents`), não na do formulário
    const priceError = (errors as Record<string, string>).price_cents;

    const submit = () => {
        // a tela digita reais; o servidor só aceita centavos
        transform((payload) => ({
            ...payload,
            price_cents: Math.round(Number(String(payload.price).replace(',', '.')) * 100),
        }));

        const options = { preserveScroll: true, onSuccess: onClose };

        if (service) {
            put(`/painel/servicos/${service.id}`, options);
        } else {
            post('/painel/servicos', options);
        }
    };

    return (
        <div className="border-primary/40 bg-card space-y-4 rounded-[1.125rem] border p-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label htmlFor="service-name">Nome</Label>
                    <Input id="service-name" value={data.name} onChange={(event) => setData('name', event.target.value)} />
                    {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="service-description">Descrição</Label>
                    <Input id="service-description" value={data.description} onChange={(event) => setData('description', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="service-duration">Duração (min)</Label>
                    <Input
                        id="service-duration"
                        type="number"
                        min={5}
                        step={5}
                        value={data.duration_min}
                        onChange={(event) => setData('duration_min', Number(event.target.value))}
                    />
                    {errors.duration_min && <p className="text-destructive text-xs">{errors.duration_min}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="service-price">Preço (R$)</Label>
                    <Input id="service-price" inputMode="decimal" value={data.price} onChange={(event) => setData('price', event.target.value)} />
                    {priceError && <p className="text-destructive text-xs">{priceError}</p>}
                </div>
            </div>

            <label className="text-muted-foreground flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.active} onChange={(event) => setData('active', event.target.checked)} />
                Aparece no site
            </label>

            <div className="flex justify-end gap-2">
                <Button size="sm" variant="ghost" onClick={onClose}>
                    <X className="size-4" /> Cancelar
                </Button>
                <Button size="sm" disabled={processing} onClick={submit}>
                    <Check className="size-4" /> Salvar
                </Button>
            </div>
        </div>
    );
}
