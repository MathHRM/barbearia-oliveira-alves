import { BrandMark } from '@/components/booking/brand-mark';
import { Button } from '@/components/ui/button';
import { brl, longDate } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { CalendarPlus, CheckCircle2, MapPin, XCircle } from 'lucide-react';

export interface TrackedAppointment {
    token: string;
    code: string;
    status: 'confirmed' | 'attended' | 'no_show' | 'canceled' | 'expired';
    status_label: string;
    starts_at: string;
    date: string;
    time: string;
    price_cents: number;
    duration_min: number;
    service: string;
    barber: string;
    customer: string;
    cancelable: boolean;
    payment_method: 'pix' | 'card' | 'cash';
}

interface Props {
    appointment: TrackedAppointment;
    shop: { name: string; address: string; cancel_window_hours: number };
}

export default function Acompanhamento({ appointment, shop }: Props) {
    return (
        <div className="bg-background text-foreground min-h-svh">
            <Head title={appointment.status_label} />

            <header className="border-border/80 border-b px-4 py-5">
                <BrandMark />
            </header>

            <main className="mx-auto w-full max-w-xl space-y-6 px-4 py-8">
                {appointment.status === 'confirmed' || appointment.status === 'attended' ? (
                    <>
                        <div className="flex flex-col items-center gap-3 text-center">
                            <CheckCircle2 className="text-success size-10" />
                            <p className="eyebrow">Passo 06 · Pronto</p>
                            <h1 className="text-[1.75rem] leading-tight">Horário confirmado</h1>
                            <p className="tabular text-muted-foreground text-sm">{appointment.code}</p>
                        </div>

                        <Summary appointment={appointment} />

                        <div className="border-border bg-card flex items-start gap-3 rounded-[1.125rem] border p-4">
                            <MapPin className="text-primary mt-0.5 size-4" />
                            <div>
                                <p className="font-display text-sm font-semibold">{shop.name}</p>
                                <p className="text-muted-foreground text-sm">{shop.address}</p>
                            </div>
                        </div>

                        <a href={`/agendamentos/${appointment.token}/agenda.ics`}>
                            <Button variant="outline" className="w-full" size="lg">
                                <CalendarPlus className="size-4" /> Adicionar ao calendário
                            </Button>
                        </a>

                        {appointment.cancelable && (
                            <button
                                type="button"
                                onClick={() => router.post(`/agendamentos/${appointment.token}/cancelar`)}
                                className="text-muted-foreground hover:text-destructive w-full text-center text-xs underline underline-offset-4"
                            >
                                Cancelar agendamento
                            </button>
                        )}
                    </>
                ) : (
                    <>
                        <div className="flex flex-col items-center gap-3 text-center">
                            <XCircle className="text-destructive size-10" />
                            <h1 className="text-[1.75rem] leading-tight">{appointment.status_label}</h1>
                            <p className="text-muted-foreground text-sm">
                                'Esse agendamento não está mais valendo.'
                            </p>
                        </div>

                        <Button className="w-full" size="lg" onClick={() => router.visit('/')}>
                            Agendar de novo
                        </Button>
                    </>
                )}
            </main>
        </div>
    );
}

function Summary({ appointment }: { appointment: TrackedAppointment }) {
    return (
        <div className="border-border bg-card rounded-[1.125rem] border p-4">
            <p className="font-display text-base font-semibold">{appointment.service}</p>
            <p className="text-muted-foreground mt-1 text-sm">
                {longDate(appointment.date)} · <span className="tabular">{appointment.time}</span> · com {appointment.barber}
            </p>
            <p className="tabular text-primary mt-2 text-lg font-semibold">{brl(appointment.price_cents)}</p>
        </div>
    );
}
