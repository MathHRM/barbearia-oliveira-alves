import { CancelDialog } from '@/components/painel/cancel-dialog';
import { ManualAppointmentDialog } from '@/components/painel/manual-appointment-dialog';
import { StatCard } from '@/components/painel/stat-card';
import { StatusBadge } from '@/components/painel/status-badge';
import { Button } from '@/components/ui/button';
import { CheckboxField } from '@/components/ui/checkbox-field';
import { DateInput, TimeInput } from '@/components/ui/date-time-input';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { PainelLayout } from '@/layouts/painel-layout';
import { brl, isoDate, longDate, relativeDay } from '@/lib/format';
import type { AgendaBlock, AgendaRow, AgendaTotals, PainelBarber, PainelService } from '@/types/painel';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, CalendarOff, Check, ChevronLeft, ChevronRight, Trash2, X } from 'lucide-react';
import { useState } from 'react';

type ViewMode = 'day' | 'week' | 'month';

interface Props {
    date: string;
    view: ViewMode;
    rangeStart: string;
    rangeEnd: string;
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

export default function Agenda({
    date,
    view,
    rangeStart,
    rangeEnd,
    prev,
    next,
    today,
    barberId,
    barbers,
    rows,
    blocks,
    totals,
    services,
    can,
}: Props) {
    const [canceling, setCanceling] = useState<AgendaRow | null>(null);
    const [selectedDay, setSelectedDay] = useState<string | null>(null);

    const go = (patch: Record<string, string | number | null>) => {
        router.get('/painel/agenda', { date, view, barber_id: barberId, ...patch }, { preserveScroll: true, preserveState: true });
    };

    const title = view === 'day' ? longDate(date) : view === 'week' ? `Semana de ${longDate(rangeStart)}` : monthLabel(date);
    const selectedRows = selectedDay ? rows.filter((row) => row.date === selectedDay) : [];

    return (
        <PainelLayout
            title="Agenda"
            subtitle={title}
            actions={
                <div className="flex justify-end">
                    <ManualAppointmentDialog date={date} services={services} barbers={barbers} canPickBarber={can.filter_barbers} />
                </div>
            }
        >
            <Head title="Agenda" />

            <div className="mb-5 flex flex-wrap items-center gap-2">
                <Button className="min-h-11 px-2 sm:min-h-9 sm:px-3" variant="outline" size="sm" onClick={() => go({ date: prev })}>
                    <ChevronLeft className="size-4" /> <span className="hidden sm:inline">Anterior</span>
                </Button>
                <DateInput
                    value={date}
                    onChange={(value) => go({ date: value })}
                    className="min-w-0 flex-1 sm:w-[11.5rem] sm:flex-none"
                    aria-label="Data de referência da agenda"
                />
                <Button className="min-h-11 px-2 sm:min-h-9 sm:px-3" variant="outline" size="sm" onClick={() => go({ date: next })}>
                    <span className="hidden sm:inline">Próximo</span> <ChevronRight className="size-4" />
                </Button>
                {date !== today && (
                    <Button className="min-h-10 sm:min-h-9" variant="ghost" size="sm" onClick={() => go({ date: today })}>
                        Hoje
                    </Button>
                )}

                <div
                    className="border-border bg-muted/40 flex w-full rounded-lg border p-1 sm:ml-2 sm:w-auto"
                    role="group"
                    aria-label="Visualização da agenda"
                >
                    {(['day', 'week', 'month'] as const).map((option) => (
                        <button
                            key={option}
                            type="button"
                            aria-pressed={view === option}
                            onClick={() => go({ view: option })}
                            className={`min-h-10 flex-1 rounded-md px-3 text-sm font-medium transition sm:min-h-8 sm:flex-none ${view === option ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            {viewLabel(option)}
                        </button>
                    ))}
                </div>

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
                        <p className="eyebrow">{view === 'day' ? `${relativeDay(date, today)} em números` : `${viewLabel(view)} em números`}</p>
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

                <div className="order-last min-w-0 lg:order-first">
                    {view === 'day' && <DayView rows={rows} showBarber={barberId === null} onCancel={setCanceling} />}
                    {view === 'week' && <WeekView rows={rows} rangeStart={rangeStart} rangeEnd={rangeEnd} onOpenDay={setSelectedDay} />}
                    {view === 'month' && <MonthView rows={rows} date={date} rangeStart={rangeStart} rangeEnd={rangeEnd} onOpenDay={setSelectedDay} />}
                </div>
            </div>

            <CancelDialog row={canceling} onClose={() => setCanceling(null)} />
            <DayAppointmentsDialog
                date={selectedDay}
                rows={selectedRows}
                showBarber={barberId === null}
                onClose={() => setSelectedDay(null)}
                onCancel={setCanceling}
            />
        </PainelLayout>
    );
}

function viewLabel(view: ViewMode): string {
    return view === 'day' ? 'Dia' : view === 'week' ? 'Semana' : 'Mês';
}

function monthLabel(date: string): string {
    const [year, month] = date.split('-').map(Number);

    return new Date(year, month - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
}

function dateRange(start: string, end: string): string[] {
    const [startYear, startMonth, startDay] = start.split('-').map(Number);
    const [endYear, endMonth, endDay] = end.split('-').map(Number);
    const current = new Date(startYear, startMonth - 1, startDay);
    const last = new Date(endYear, endMonth - 1, endDay);
    const dates: string[] = [];

    while (current <= last) {
        dates.push(isoDate(current));
        current.setDate(current.getDate() + 1);
    }

    return dates;
}

function DayView({ rows, showBarber, onCancel }: { rows: AgendaRow[]; showBarber: boolean; onCancel: (row: AgendaRow) => void }) {
    if (rows.length === 0) {
        return <EmptyAgenda label="Nenhum agendamento nesse dia." />;
    }

    return (
        <div className="space-y-3">
            {rows.map((row) => (
                <Row key={row.id} row={row} showBarber={showBarber} onCancel={() => onCancel(row)} />
            ))}
        </div>
    );
}

function WeekView({
    rows,
    rangeStart,
    rangeEnd,
    onOpenDay,
}: {
    rows: AgendaRow[];
    rangeStart: string;
    rangeEnd: string;
    onOpenDay: (date: string) => void;
}) {
    const days = dateRange(rangeStart, rangeEnd);
    const hours = Array.from(new Set(rows.map((row) => row.starts_at))).sort();
    const byDateAndHour = rows.reduce<Record<string, AgendaRow[]>>((grouped, row) => {
        (grouped[`${row.date}-${row.starts_at}`] ??= []).push(row);
        return grouped;
    }, {});

    if (hours.length === 0) {
        return <EmptyAgenda label="Nenhum agendamento nessa semana." />;
    }

    return (
        <div className="border-border bg-card overflow-hidden rounded-[1.125rem] border">
            <div className="grid grid-cols-[3.75rem_repeat(7,minmax(0,1fr))] border-b">
                <div className="text-muted-foreground flex items-center justify-center p-2 text-[10px] font-semibold tracking-[0.08em] uppercase">
                    Hora
                </div>
                {days.map((day) => (
                    <WeekDayHeader key={day} date={day} onOpenDay={onOpenDay} />
                ))}
            </div>
            {hours.map((hour) => (
                <div key={hour} className="border-border/70 grid grid-cols-[3.75rem_repeat(7,minmax(0,1fr))] border-b last:border-b-0">
                    <div className="text-muted-foreground bg-muted/20 tabular border-border/70 flex min-h-20 items-start justify-center border-r px-1 pt-3 text-xs font-semibold">
                        {hour}
                    </div>
                    {days.map((day) => (
                        <div key={`${day}-${hour}`} className="border-border/70 min-h-20 border-r p-1.5 last:border-r-0">
                            {(byDateAndHour[`${day}-${hour}`] ?? []).map((row) => (
                                <CompactAppointment key={row.id} row={row} onClick={() => onOpenDay(row.date)} />
                            ))}
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}

function WeekDayHeader({ date, onOpenDay }: { date: string; onOpenDay: (date: string) => void }) {
    const parsed = new Date(`${date}T12:00:00`);
    const heading = parsed.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');

    return (
        <button type="button" onClick={() => onOpenDay(date)} className="border-border/70 group hover:bg-accent/50 min-h-14 border-l p-2 text-left">
            <span className="text-muted-foreground block text-[10px] font-medium tracking-[0.08em] uppercase">{heading}</span>
            <span className="font-display group-hover:text-primary text-base font-semibold">{parsed.getDate()}</span>
        </button>
    );
}

function MonthView({
    rows,
    date,
    rangeStart,
    rangeEnd,
    onOpenDay,
}: {
    rows: AgendaRow[];
    date: string;
    rangeStart: string;
    rangeEnd: string;
    onOpenDay: (date: string) => void;
}) {
    const first = new Date(`${rangeStart}T12:00:00`);
    const last = new Date(`${rangeEnd}T12:00:00`);
    const leading = (first.getDay() + 6) % 7;
    const calendarStart = new Date(first);
    calendarStart.setDate(first.getDate() - leading);
    const calendarEnd = new Date(last);
    calendarEnd.setDate(last.getDate() + (7 - ((last.getDay() + 6) % 7) - 1));
    const days = dateRange(isoDate(calendarStart), isoDate(calendarEnd));
    const byDate = rows.reduce<Record<string, AgendaRow[]>>((grouped, row) => {
        (grouped[row.date] ??= []).push(row);
        return grouped;
    }, {});
    const weekdays = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];

    return (
        <div className="border-border bg-card overflow-hidden rounded-[1.125rem] border">
            <div className="bg-muted/30 grid grid-cols-7 border-b">
                {weekdays.map((weekday) => (
                    <div key={weekday} className="text-muted-foreground py-2 text-center text-[11px] font-semibold tracking-[0.12em] uppercase">
                        {weekday}
                    </div>
                ))}
            </div>
            <div className="grid grid-cols-7">
                {days.map((day) => {
                    const outside = day.slice(0, 7) !== date.slice(0, 7);
                    const dayRows = byDate[day] ?? [];

                    return (
                        <button
                            key={day}
                            type="button"
                            onClick={() => onOpenDay(day)}
                            aria-label={`${longDate(day)}: ${dayRows.length} agendamento${dayRows.length === 1 ? '' : 's'}`}
                            className={`border-border/70 hover:bg-accent/50 focus-visible:ring-ring min-h-16 border-r border-b p-1 text-left align-top transition focus-visible:z-10 focus-visible:ring-2 focus-visible:outline-none sm:min-h-32 sm:p-2 ${outside ? 'bg-muted/15 text-muted-foreground' : ''}`}
                        >
                            <span
                                className={`mb-1 flex size-6 items-center justify-center rounded-full text-[11px] font-semibold sm:mb-2 sm:size-7 sm:text-xs ${day === date ? 'bg-primary text-primary-foreground' : ''}`}
                            >
                                {Number(day.slice(-2))}
                            </span>
                            <span className="flex items-center gap-1 sm:hidden">
                                {dayRows.length > 0 && <span className="bg-primary size-1.5 rounded-full" />}
                                {dayRows.length > 0 && <span className="text-muted-foreground text-[10px] font-semibold">{dayRows.length}</span>}
                            </span>
                            <span className="hidden space-y-1 sm:block">
                                {dayRows.slice(0, 3).map((row) => (
                                    <span
                                        key={row.id}
                                        className="border-primary/30 bg-primary/10 text-foreground block truncate rounded px-1.5 py-1 text-[11px]"
                                    >
                                        <span className="tabular font-semibold">{row.starts_at}</span> {row.customer.name}
                                    </span>
                                ))}
                                {dayRows.length > 3 && (
                                    <span className="text-muted-foreground block px-1 text-[11px]">+{dayRows.length - 3} horários</span>
                                )}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function CompactAppointment({ row, onClick }: { row: AgendaRow; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="border-border/80 hover:bg-accent/50 focus-visible:ring-ring block w-full rounded-lg border px-1.5 py-1.5 text-left transition focus-visible:ring-2 focus-visible:outline-none sm:px-2 sm:py-2"
        >
            <div className="flex items-center justify-between gap-1">
                <span className="tabular text-primary text-[10px] font-semibold sm:text-xs">{row.starts_at}</span>
                <span className="hidden sm:inline">
                    <StatusBadge tone={row.tone}>{row.status_label}</StatusBadge>
                </span>
            </div>
            <p className="mt-0.5 truncate text-[10px] font-semibold sm:mt-1 sm:text-xs">{row.customer.name}</p>
            <p className="text-muted-foreground mt-0.5 hidden truncate text-[11px] sm:block">{row.service}</p>
        </button>
    );
}

function DayAppointmentsDialog({
    date,
    rows,
    showBarber,
    onClose,
    onCancel,
}: {
    date: string | null;
    rows: AgendaRow[];
    showBarber: boolean;
    onClose: () => void;
    onCancel: (row: AgendaRow) => void;
}) {
    if (!date) {
        return null;
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[88dvh] overflow-y-auto p-4 sm:max-w-2xl sm:p-6">
                <DialogHeader className="pr-8">
                    <DialogTitle>{longDate(date)}</DialogTitle>
                    <DialogDescription>
                        {rows.length === 0
                            ? 'Nenhum corte marcado neste dia.'
                            : `${rows.length} corte${rows.length === 1 ? '' : 's'} marcado${rows.length === 1 ? '' : 's'}`}
                    </DialogDescription>
                </DialogHeader>
                {rows.length > 0 ? (
                    <DayView rows={rows} showBarber={showBarber} onCancel={onCancel} />
                ) : (
                    <EmptyAgenda label="Nenhum agendamento neste dia." />
                )}
            </DialogContent>
        </Dialog>
    );
}

function EmptyAgenda({ label }: { label: string }) {
    return (
        <div className="border-border text-muted-foreground flex min-h-40 flex-col items-center justify-center rounded-[1.125rem] border border-dashed p-8 text-center text-sm">
            <CalendarDays className="text-muted-foreground/60 mb-2 size-5" />
            {label}
        </div>
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
