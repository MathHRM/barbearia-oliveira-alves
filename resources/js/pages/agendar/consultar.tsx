import { BrandMark } from '@/components/booking/brand-mark';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brl, duration, longDate, maskPhone } from '@/lib/format';
import type { TrackedAppointment } from '@/pages/agendar/acompanhamento';
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Clock3, Scissors, UserRound, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Props {
    appointments: TrackedAppointment[];
    searched_phone: string | null;
}

export default function Consultar({ appointments: initialAppointments, searched_phone: initialPhone }: Props) {
    const [phone, setPhone] = useState(initialPhone ?? '');
    const [appointments, setAppointments] = useState(initialAppointments);
    const [searchedPhone, setSearchedPhone] = useState(initialPhone);
    const [error, setError] = useState<string | null>(null);
    const [phoneError, setPhoneError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [canceling, setCanceling] = useState<string | null>(null);
    const [confirming, setConfirming] = useState<TrackedAppointment | null>(null);
    const firstName = appointments[0]?.customer.trim().split(/\s+/)[0] ?? '';

    const csrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

    const search = async (event: FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setError(null);
        setPhoneError(null);
        setConfirming(null);

        try {
            const response = await fetch('/agendamentos/consultar', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': csrf() },
                body: JSON.stringify({ phone }),
            });
            const payload = await response.json();

            if (response.status === 422) {
                setPhoneError(payload.errors?.phone?.[0] ?? 'Confira o WhatsApp informado.');
                setAppointments([]);
                return;
            }

            if (!response.ok) {
                setError('Não conseguimos consultar agora. Tente de novo.');
                return;
            }

            setAppointments(payload.appointments ?? []);
            setSearchedPhone(payload.phone ?? phone);
        } catch {
            setError('Falha de conexão. Tente de novo.');
        } finally {
            setLoading(false);
        }
    };

    const cancel = async (appointment: TrackedAppointment) => {
        setCanceling(appointment.token);
        setError(null);

        try {
            const response = await fetch(`/agendamentos/${appointment.token}/cancelar`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
            });

            if (!response.ok) {
                setError('Esse agendamento não pode mais ser cancelado. Atualize a consulta e tente novamente.');
                return;
            }

            setAppointments((current) => current.filter((item) => item.token !== appointment.token));
            setConfirming(null);
        } catch {
            setError('Falha de conexão. Tente de novo.');
        } finally {
            setCanceling(null);
        }
    };

    return (
        <div className="bg-background text-foreground min-h-svh">
            <Head title="Meus agendamentos" />

            <header className="border-border/80 border-b px-4 py-5">
                <div className="mx-auto flex w-full max-w-xl items-center justify-between">
                    <Link
                        href="/"
                        aria-label="Voltar para o início"
                        className="focus-visible:ring-ring rounded-md focus-visible:ring-2 focus-visible:outline-hidden"
                    >
                        <BrandMark className="items-start" />
                    </Link>
                    <Button asChild size="sm">
                        <Link href="/agendar">Agendar</Link>
                    </Button>
                </div>
            </header>

            <main className="mx-auto w-full max-w-xl space-y-8 px-4 py-8">
                <div>
                    {firstName ? (
                        <>
                            <p className="eyebrow">Meus agendamentos</p>
                            <h1 className="mt-2 text-3xl leading-tight sm:text-4xl">Olá, {firstName}!</h1>
                            <p className="text-muted-foreground mt-2 text-sm sm:text-base">Estes são seus agendamentos.</p>
                        </>
                    ) : (
                        <>
                            <p className="eyebrow">Área do cliente</p>
                            <h1 className="mt-1 text-[1.75rem] leading-tight">Meus agendamentos</h1>
                            <p className="text-muted-foreground mt-2 text-sm">Informe o WhatsApp usado na reserva para ver seus próximos horários.</p>
                        </>
                    )}
                </div>

                <form onSubmit={search} className="border-border bg-card space-y-4 rounded-[1.125rem] border p-4">
                    <div className="space-y-2">
                        <Label htmlFor="lookup-phone">WhatsApp</Label>
                        <Input
                            id="lookup-phone"
                            type="tel"
                            inputMode="tel"
                            autoComplete="tel"
                            placeholder="(11) 98888-7777"
                            value={phone}
                            onChange={(event) => setPhone(maskPhone(event.target.value))}
                            aria-invalid={Boolean(phoneError)}
                            aria-describedby={phoneError ? 'lookup-phone-error' : undefined}
                        />
                        {phoneError && (
                            <p id="lookup-phone-error" className="text-destructive text-xs">
                                {phoneError}
                            </p>
                        )}
                    </div>
                    <Button type="submit" className="w-full" size="lg" disabled={loading}>
                        {loading ? 'Consultando…' : 'Consultar agendamentos'}
                    </Button>
                </form>

                {error && (
                    <p className="bg-destructive/10 text-destructive rounded-md p-3 text-sm" role="alert">
                        {error}
                    </p>
                )}

                {searchedPhone && appointments.length > 0 && (
                    <section className="space-y-4" aria-live="polite">
                        <div className="border-primary/40 bg-primary/5 rounded-[1.125rem] border p-4">
                            <p className="eyebrow text-primary">Próxima visita</p>
                            <p className="mt-2 text-lg font-semibold">{longDate(appointments[0].date)}</p>
                            <p className="tabular text-primary mt-1 text-2xl font-semibold">{appointments[0].time}</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {appointments[0].service} · {appointments[0].barber}
                            </p>
                        </div>
                        <div className="space-y-3">
                            {appointments.map((appointment) => (
                                <AppointmentCard
                                    key={appointment.token}
                                    appointment={appointment}
                                    onCancel={() => setConfirming(appointment)}
                                    canceling={canceling === appointment.token}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {searchedPhone && appointments.length === 0 && !loading && !phoneError && (
                    <div className="border-border text-muted-foreground rounded-[1.125rem] border border-dashed p-6 text-center" role="status">
                        <CalendarDays className="text-primary mx-auto size-8" />
                        <p className="text-foreground mt-3 font-semibold">Nenhum agendamento encontrado</p>
                        <p className="mt-1 text-sm">Não encontramos horários agendados para este WhatsApp.</p>
                    </div>
                )}
            </main>

            {confirming && (
                <div
                    className="bg-background/90 fixed inset-0 z-20 flex items-end justify-center p-4 backdrop-blur-sm sm:items-center"
                    role="presentation"
                >
                    <div
                        className="border-border bg-card w-full max-w-md space-y-5 rounded-[1.125rem] border p-5 shadow-xl"
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="cancel-title"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="eyebrow text-destructive">Confirmação</p>
                                <h2 id="cancel-title" className="mt-1 text-xl">
                                    Cancelar este agendamento?
                                </h2>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    {longDate(confirming.date)} às <span className="tabular">{confirming.time}</span>, {confirming.service}.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setConfirming(null)}
                                aria-label="Fechar confirmação"
                                className="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-md p-1 focus-visible:ring-2"
                            >
                                <X className="size-5" />
                            </button>
                        </div>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <Button variant="outline" onClick={() => setConfirming(null)}>
                                Manter agendamento
                            </Button>
                            <Button variant="destructive" disabled={Boolean(canceling)} onClick={() => cancel(confirming)}>
                                {canceling ? 'Cancelando…' : 'Sim, cancelar'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function AppointmentCard({ appointment, onCancel, canceling }: { appointment: TrackedAppointment; onCancel: () => void; canceling: boolean }) {
    return (
        <article className="border-border bg-card rounded-[1.125rem] border p-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="font-display font-semibold">{appointment.service}</p>
                    <p className="text-muted-foreground mt-1 text-sm">{longDate(appointment.date)}</p>
                </div>
                <p className="tabular text-primary text-lg font-semibold">{appointment.time}</p>
            </div>
            <div className="text-muted-foreground mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                <span className="flex items-center gap-1.5">
                    <UserRound className="size-3.5" />
                    {appointment.barber}
                </span>
                <span className="flex items-center gap-1.5">
                    <Clock3 className="size-3.5" />
                    {duration(appointment.duration_min)}
                </span>
                <span className="flex items-center gap-1.5">
                    <Scissors className="size-3.5" />
                    {brl(appointment.price_cents)}
                </span>
                <span className="tabular">{appointment.code}</span>
            </div>
            {appointment.cancelable ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onCancel}
                    disabled={canceling}
                    className="text-destructive hover:text-destructive mt-5 w-full hover:bg-transparent sm:w-auto"
                >
                    {canceling ? 'Cancelando…' : 'Cancelar'}
                </Button>
            ) : (
                <p className="text-muted-foreground mt-5 text-xs">Cancelamento encerrado para este horário.</p>
            )}
        </article>
    );
}
