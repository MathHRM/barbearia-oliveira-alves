import { CancelDialog } from '@/components/painel/cancel-dialog';
import { ManualAppointmentDialog } from '@/components/painel/manual-appointment-dialog';
import { StatCard } from '@/components/painel/stat-card';
import { StatusBadge } from '@/components/painel/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PainelLayout } from '@/layouts/painel-layout';
import { brl, longDate } from '@/lib/format';
import type { AgendaBlock, AgendaRow, AgendaTotals, PainelBarber, PainelService } from '@/types/painel';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarOff, Check, ChevronLeft, ChevronRight, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface Props {
    date: string;
    prev: string;
    next: string;
    today: string;
    barberId: number | null;
    barbers: PainelBarber[];
    rows: AgendaRow[];
    blocks: AgendaBlock[];
    totals: AgendaTotals;
    services: PainelService[];
    can: { see_revenue: boolean; filter_barbers: boolean };
}

export default function Agenda({ date, prev, next, today, barberId, barbers, rows, blocks, totals, services, can }: Props) {
    const [canceling, setCanceling] = useState<AgendaRow | null>(null);

    const go = (patch: Record<string, string | number | null>) => {
        router.get('/painel/agenda', { date, barber_id: barberId, ...patch }, { preserveScroll: true, preserveState: true });
    };

    return (
        <PainelLayout
            title="Agenda"
            subtitle={longDate(date)}
            actions={<ManualAppointmentDialog date={date} services={services} barbers={barbers} canPickBarber={can.filter_barbers} />}
        >
            <Head title="Agenda" />

            <div className="mb-6 flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" onClick={() => go({ date: prev })}>
                    <ChevronLeft className="size-4" /> Ontem
                </Button>
                <Input type="date" value={date} onChange={(event) => go({ date: event.target.value })} className="h-9 w-[10.5rem]" />
                <Button variant="outline" size="sm" onClick={() => go({ date: next })}>
                    Amanhã <ChevronRight className="size-4" />
                </Button>
                {date !== today && (
                    <Button variant="ghost" size="sm" onClick={() => go({ date: today })}>
                        Hoje
                    </Button>
                )}

                {can.filter_barbers && (
                    <select
                        value={barberId ?? ''}
                        onChange={(event) => go({ barber_id: event.target.value === '' ? null : Number(event.target.value) })}
                        className="border-input bg-background ml-auto h-9 rounded-md border px-3 text-sm"
                    >
                        <option value="">Todos os profissionais</option>
                        {barbers.map((barber) => (
                            <option key={barber.id} value={barber.id}>
                                {barber.name}
                            </option>
                        ))}
                    </select>
                )}
            </div>

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="space-y-3">
                    {rows.length === 0 ? (
                        <div className="border-border text-muted-foreground rounded-[1.125rem] border border-dashed p-10 text-center text-sm">
                            Nenhum agendamento nesse dia.
                        </div>
                    ) : (
                        rows.map((row) => <Row key={row.id} row={row} showBarber={barberId === null} onCancel={() => setCanceling(row)} />)
                    )}
                </div>

                <aside className="space-y-6">
                    <section className="space-y-3">
                        <p className="eyebrow">Hoje em números</p>
                        <div className="grid grid-cols-2 gap-3">
                            <StatCard label="Agendados" value={String(totals.confirmed + totals.pending)} />
                            <StatCard label="Compareceram" value={String(totals.attended)} />
                            <StatCard label="Cancelados" value={String(totals.canceled)} />
                            <StatCard label="Livres" value={String(totals.free_slots)} hint="Horários ainda vendáveis" />
                            {can.see_revenue && (
                                <>
                                    <StatCard label="Previsto" value={brl(totals.expected_cents ?? 0)} />
                                    <StatCard label="Recebido" value={brl(totals.received_cents ?? 0)} />
                                </>
                            )}
                        </div>
                    </section>

                    <BlockPanel date={date} barbers={barbers} barberId={barberId} blocks={blocks} canPickBarber={can.filter_barbers} />
                </aside>
            </div>

            <CancelDialog row={canceling} onClose={() => setCanceling(null)} />
        </PainelLayout>
    );
}

function Row({ row, showBarber, onCancel }: { row: AgendaRow; showBarber: boolean; onCancel: () => void }) {
    const act = (action: string) => router.post(`/painel/agendamentos/${row.id}/${action}`, {}, { preserveScroll: true });

    return (
        <article className="border-border bg-card flex flex-wrap items-center gap-4 rounded-[1.125rem] border p-4">
            <div className="w-14 shrink-0">
                <p className="tabular text-lg font-semibold">{row.starts_at}</p>
                <p className="tabular text-muted-foreground text-xs">{row.ends_at}</p>
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <p className="font-display truncate text-base font-semibold">{row.customer.name}</p>
                    <StatusBadge tone={row.tone}>{row.status_label}</StatusBadge>
                </div>
                <p className="text-muted-foreground mt-1 text-sm">
                    {row.service} · <span className="tabular">{row.customer.phone}</span> · {row.customer.visits} visita
                    {row.customer.visits === 1 ? '' : 's'}
                    {showBarber ? ` · ${row.barber}` : ''}
                </p>
                <p className="text-muted-foreground mt-0.5 text-xs">
                    {row.origin}
                    {row.payment ? ` · ${row.payment.billing_type === 'PIX' ? 'Pix' : 'Cartão'}` : ' · pagamento no caixa'}
                    {row.note ? ` · "${row.note}"` : ''}
                </p>
            </div>

            <p className="tabular text-primary w-20 text-right font-semibold">{brl(row.price_cents)}</p>

            <div className="flex gap-2">
                {row.can_attend && (
                    <>
                        <Button size="sm" variant="outline" onClick={() => act('compareceu')}>
                            <Check className="size-4" /> Compareceu
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => act('faltou')}>
                            <X className="size-4" /> Faltou
                        </Button>
                    </>
                )}
                {row.can_cancel && (
                    <Button size="sm" variant="ghost" className="text-destructive" onClick={onCancel}>
                        Cancelar
                    </Button>
                )}
            </div>
        </article>
    );
}

/** Bloqueio some da agenda pública na hora — almoço, dentista, dia cheio. */
function BlockPanel({
    date,
    barbers,
    barberId,
    blocks,
    canPickBarber,
}: {
    date: string;
    barbers: PainelBarber[];
    barberId: number | null;
    blocks: AgendaBlock[];
    canPickBarber: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        barber_id: barberId ?? barbers[0]?.id ?? 0,
        date,
        starts: '',
        ends: '',
        reason: '',
    });

    const submit = () => {
        post('/painel/bloqueios', {
            preserveScroll: true,
            onSuccess: () => reset('starts', 'ends', 'reason'),
        });
    };

    return (
        <section className="border-border bg-card space-y-4 rounded-[1.125rem] border p-4">
            <div className="flex items-center gap-2">
                <CalendarOff className="text-primary size-4" />
                <p className="font-display text-sm font-semibold">Bloquear horário</p>
            </div>

            {canPickBarber && (
                <select
                    value={data.barber_id}
                    onChange={(event) => setData('barber_id', Number(event.target.value))}
                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                >
                    {barbers.map((barber) => (
                        <option key={barber.id} value={barber.id}>
                            {barber.name}
                        </option>
                    ))}
                </select>
            )}

            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                    <Label htmlFor="block-starts" className="text-xs">
                        De
                    </Label>
                    <Input id="block-starts" type="time" value={data.starts} onChange={(event) => setData('starts', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="block-ends" className="text-xs">
                        Até
                    </Label>
                    <Input id="block-ends" type="time" value={data.ends} onChange={(event) => setData('ends', event.target.value)} />
                </div>
            </div>

            <Input value={data.reason} onChange={(event) => setData('reason', event.target.value)} placeholder="Motivo (opcional)" />
            {errors.ends && <p className="text-destructive text-xs">{errors.ends}</p>}

            <Button size="sm" className="w-full" disabled={processing} onClick={submit}>
                Bloquear
            </Button>

            {blocks.length > 0 && (
                <ul className="border-border/80 space-y-2 border-t pt-3">
                    {blocks.map((block) => (
                        <li key={block.id} className="flex items-center gap-2 text-sm">
                            <span className="tabular">
                                {block.starts_at}–{block.ends_at}
                            </span>
                            <span className="text-muted-foreground min-w-0 flex-1 truncate text-xs">{block.reason ?? block.barber}</span>
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-destructive"
                                onClick={() => router.delete(`/painel/bloqueios/${block.id}`, { preserveScroll: true })}
                                aria-label="Remover bloqueio"
                            >
                                <Trash2 className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
