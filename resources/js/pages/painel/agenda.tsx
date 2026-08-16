import { CancelDialog } from '@/components/painel/cancel-dialog';
import { ManualAppointmentDialog } from '@/components/painel/manual-appointment-dialog';
import { StatCard } from '@/components/painel/stat-card';
import { StatusBadge } from '@/components/painel/status-badge';
import { Button } from '@/components/ui/button';
import { CheckboxField } from '@/components/ui/checkbox-field';
import { DateInput, TimeInput } from '@/components/ui/date-time-input';
import { Input } from '@/components/ui/input';
import { PainelLayout } from '@/layouts/painel-layout';
import { brl, longDate, relativeDay } from '@/lib/format';
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
            actions={
                <div className="flex justify-end">
                    <ManualAppointmentDialog date={date} services={services} barbers={barbers} canPickBarber={can.filter_barbers} />
                </div>
            }
        >
            <Head title="Agenda" />

            <div className="mb-5 grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 sm:flex sm:flex-wrap">
                <Button className="min-h-11 px-2 sm:min-h-9 sm:px-3" variant="outline" size="sm" onClick={() => go({ date: prev })}>
                    <ChevronLeft className="size-4" /> <span className="hidden sm:inline">Anterior</span>
                </Button>
                <DateInput value={date} onChange={(value) => go({ date: value })} className="w-full sm:w-[11.5rem]" aria-label="Dia da agenda" />
                <Button className="min-h-11 px-2 sm:min-h-9 sm:px-3" variant="outline" size="sm" onClick={() => go({ date: next })}>
                    <span className="hidden sm:inline">Próximo</span> <ChevronRight className="size-4" />
                </Button>
                {date !== today && (
                    <Button className="col-span-3 min-h-10 sm:min-h-9" variant="ghost" size="sm" onClick={() => go({ date: today })}>
                        Hoje
                    </Button>
                )}

                {can.filter_barbers && (
                    <select
                        value={barberId ?? ''}
                        onChange={(event) => go({ barber_id: event.target.value === '' ? null : Number(event.target.value) })}
                        className="border-input bg-background col-span-3 h-11 w-full rounded-md border px-3 text-sm sm:ml-auto sm:h-9 sm:w-auto"
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

            <div className="grid gap-5 lg:grid-cols-[1fr_20rem] lg:gap-6">
                <aside className="order-first space-y-5 lg:order-last lg:space-y-6">
                    <section className="space-y-3">
                        <p className="eyebrow">{relativeDay(date, today)} em números</p>
                        <div className="grid grid-cols-2 gap-2.5 sm:gap-3">
                            <StatCard label="Agendados" value={String(totals.scheduled)} />
                            <StatCard label="Compareceram" value={String(totals.attended)} />
                            <StatCard label="Cancelados" value={String(totals.canceled)} />
                            <StatCard label="Livres" value={String(totals.free_slots)} hint="Horários ainda vendáveis" />
                            {can.see_revenue && (
                                <>
                                    <StatCard label="Valor estimado" value={brl(totals.estimated_cents ?? 0)} />
                                    <StatCard label="Valor ganho" value={brl(totals.earned_cents ?? 0)} />
                                </>
                            )}
                        </div>
                    </section>

                    {/* key no dia: trocar de data reseta o formulário para o dia visível */}
                    <BlockPanel key={date} date={date} barbers={barbers} barberId={barberId} blocks={blocks} canPickBarber={can.filter_barbers} />
                </aside>

                <div className="order-last space-y-3 lg:order-first">
                    {rows.length === 0 ? (
                        <div className="border-border text-muted-foreground rounded-[1.125rem] border border-dashed p-8 text-center text-sm sm:p-10">
                            Nenhum agendamento nesse dia.
                        </div>
                    ) : (
                        rows.map((row) => <Row key={row.id} row={row} showBarber={barberId === null} onCancel={() => setCanceling(row)} />)
                    )}
                </div>
            </div>

            <CancelDialog row={canceling} onClose={() => setCanceling(null)} />
        </PainelLayout>
    );
}

function Row({ row, showBarber, onCancel }: { row: AgendaRow; showBarber: boolean; onCancel: () => void }) {
    const act = (action: string) => router.post(`/painel/agendamentos/${row.id}/${action}`, {}, { preserveScroll: true });

    return (
        <article className="border-border bg-card flex flex-col gap-3 rounded-[1.125rem] border p-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
            <div className="flex items-start gap-3 sm:block sm:w-14 sm:shrink-0">
                <div>
                    <p className="tabular text-lg font-semibold">{row.starts_at}</p>
                    <p className="tabular text-muted-foreground text-xs">{row.ends_at}</p>
                </div>
                <p className="text-muted-foreground ml-auto text-xs sm:hidden">{row.origin}</p>
            </div>

            <div className="min-w-0 flex-1 sm:min-w-[12rem]">
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
                    {` · ${row.payment_method === 'pix' ? 'Pix' : row.payment_method === 'card' ? 'Cartão' : 'Dinheiro'}`}
                    {row.note ? ` · "${row.note}"` : ''}
                </p>
            </div>

            <p className="tabular text-primary text-right font-semibold sm:w-20">{brl(row.price_cents)}</p>

            <div className="flex flex-wrap gap-2 sm:ml-auto">
                {row.can_cancel && (
                    <Button size="sm" variant="ghost" className="text-destructive min-h-11 flex-1 sm:min-h-9 sm:flex-none" onClick={onCancel}>
                        Cancelar
                    </Button>
                )}
                {row.can_no_show && (
                    <Button className="min-h-11 flex-1 sm:min-h-9 sm:flex-none" size="sm" variant="ghost" onClick={() => act('faltou')}>
                        <X className="size-4" /> Faltou
                    </Button>
                )}
                {row.can_attend && (
                    <Button className="min-h-11 flex-1 sm:min-h-9 sm:flex-none" size="sm" variant="outline" onClick={() => act('compareceu')}>
                        <Check className="size-4" /> Compareceu
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
    const [range, setRange] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        barber_id: barberId ?? barbers[0]?.id ?? 0,
        date,
        until: '',
        starts: '',
        ends: '',
        reason: '',
    });

    const submit = () => {
        post('/painel/bloqueios', {
            preserveScroll: true,
            onSuccess: () => {
                reset('starts', 'ends', 'reason', 'until');
                setRange(false);
            },
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

            {/* dias em uma linha, horas em outra — o par nunca fica quebrado entre linhas */}
            <div className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                    <DateInput label={range ? 'Do dia' : 'Dia'} value={data.date} onChange={(value) => setData('date', value)} error={errors.date} />
                    {range && (
                        <DateInput
                            label="Até o dia"
                            min={data.date}
                            value={data.until}
                            onChange={(value) => setData('until', value)}
                            error={errors.until}
                        />
                    )}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <TimeInput label="Das" value={data.starts} onChange={(value) => setData('starts', value)} error={errors.starts} />
                    <TimeInput label="Às" value={data.ends} onChange={(value) => setData('ends', value)} error={errors.ends} />
                </div>
            </div>

            <CheckboxField
                checked={range}
                onChange={(checked) => {
                    setRange(checked);
                    setData('until', checked ? data.date : '');
                }}
                label="Repetir por vários dias"
                className="text-muted-foreground"
            />

            <Input value={data.reason} onChange={(event) => setData('reason', event.target.value)} placeholder="Motivo (opcional)" />

            <Button size="sm" className="w-full" disabled={processing} onClick={submit}>
                Bloquear
            </Button>

            {blocks.length > 0 && (
                <ul className="border-border/80 space-y-3 border-t pt-3">
                    {blocks.map((block) => (
                        <li key={block.id} className="flex items-start gap-2 text-sm">
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-x-2">
                                    <span className="tabular font-medium">
                                        {block.days > 1 ? `${block.first_day}–${block.last_day}` : block.first_day} · {block.starts_at}–
                                        {block.ends_at}
                                    </span>
                                    <span className="text-muted-foreground text-xs">{block.barber}</span>
                                </p>
                                {block.days > 1 && <p className="text-muted-foreground text-xs">{block.days} dias bloqueados</p>}
                                {block.reason && <p className="truncate text-xs">{block.reason}</p>}
                                <p className="text-muted-foreground text-xs">
                                    bloqueado por {block.created_by ?? 'usuário removido'} · {block.created_at}
                                </p>
                            </div>
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-destructive mt-0.5"
                                onClick={() => router.delete(`/painel/bloqueios/${block.id}`, { preserveScroll: true })}
                                aria-label={block.days > 1 ? 'Remover período bloqueado' : 'Remover bloqueio'}
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
