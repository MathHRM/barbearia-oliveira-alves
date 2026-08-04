import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PainelLayout } from '@/layouts/painel-layout';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface BarberRow {
    id: number;
    name: string;
    headline: string | null;
    initials: string;
    active: boolean;
    appointments_count: number;
    user: { name: string | null; email: string | null; role: string | null };
}

export default function Barbeiros({ barbers }: { barbers: BarberRow[] }) {
    const [editing, setEditing] = useState<number | 'new' | null>(null);

    return (
        <PainelLayout
            title="Barbeiros"
            subtitle="Cada barbeiro tem um acesso ao painel e enxerga só a própria agenda."
            actions={
                <Button size="sm" onClick={() => setEditing('new')}>
                    <Plus className="size-4" /> Novo barbeiro
                </Button>
            }
        >
            <Head title="Barbeiros" />

            <div className="space-y-3">
                {editing === 'new' && <BarberForm onClose={() => setEditing(null)} />}

                {barbers.map((barber) =>
                    editing === barber.id ? (
                        <BarberForm key={barber.id} barber={barber} onClose={() => setEditing(null)} />
                    ) : (
                        <article key={barber.id} className="border-border bg-card flex flex-wrap items-center gap-4 rounded-[1.125rem] border p-4">
                            <span className="bg-primary/10 text-primary font-display flex size-11 items-center justify-center rounded-full text-sm font-semibold">
                                {barber.initials}
                            </span>

                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="font-display truncate text-base font-semibold">{barber.name}</p>
                                    {!barber.active && <span className="text-muted-foreground text-xs">inativo</span>}
                                </div>
                                <p className="text-muted-foreground truncate text-sm">{barber.headline ?? '—'}</p>
                                <p className="text-muted-foreground mt-0.5 text-xs">
                                    {barber.user.email ?? 'sem acesso'} · {barber.user.role ?? '—'} · {barber.appointments_count} agendamento
                                    {barber.appointments_count === 1 ? '' : 's'}
                                </p>
                            </div>

                            <Button size="sm" variant="outline" onClick={() => setEditing(barber.id)}>
                                Editar
                            </Button>
                        </article>
                    ),
                )}
            </div>
        </PainelLayout>
    );
}

function BarberForm({ barber, onClose }: { barber?: BarberRow; onClose: () => void }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: barber?.name ?? '',
        headline: barber?.headline ?? '',
        email: barber?.user.email ?? '',
        password: '',
        password_confirmation: '',
        active: barber?.active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };

        if (barber) {
            put(`/painel/barbeiros/${barber.id}`, options);
        } else {
            post('/painel/barbeiros', options);
        }
    };

    return (
        <div className="border-primary/40 bg-card space-y-4 rounded-[1.125rem] border p-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                    <Label htmlFor="barber-name">Nome</Label>
                    <Input id="barber-name" value={data.name} onChange={(event) => setData('name', event.target.value)} />
                    {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="barber-headline">Chamada</Label>
                    <Input
                        id="barber-headline"
                        value={data.headline}
                        onChange={(event) => setData('headline', event.target.value)}
                        placeholder="Degradê e navalha"
                    />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="barber-email">E-mail de acesso</Label>
                    <Input id="barber-email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} />
                    {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="barber-password">{barber ? 'Nova senha (opcional)' : 'Senha'}</Label>
                    <Input id="barber-password" type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} />
                    {errors.password && <p className="text-destructive text-xs">{errors.password}</p>}
                </div>
                {data.password !== '' && (
                    <div className="space-y-1.5 sm:col-start-2">
                        <Label htmlFor="barber-password-confirmation">Repita a senha</Label>
                        <Input
                            id="barber-password-confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                        />
                    </div>
                )}
            </div>

            <label className="text-muted-foreground flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.active} onChange={(event) => setData('active', event.target.checked)} />
                Atende e aparece no site
            </label>

            <div className="flex justify-end gap-2">
                <Button size="sm" variant="ghost" onClick={onClose}>
                    Cancelar
                </Button>
                <Button size="sm" disabled={processing} onClick={submit}>
                    Salvar
                </Button>
            </div>
        </div>
    );
}
