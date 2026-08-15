import { StepBarber } from '@/components/booking/step-barber';
import { StepCustomer, type CustomerForm } from '@/components/booking/step-customer';
import { StepService } from '@/components/booking/step-service';
import { StepSlot } from '@/components/booking/step-slot';
import { WizardShell } from '@/components/booking/wizard-shell';
import { Button } from '@/components/ui/button';
import { useAvailability } from '@/hooks/use-availability';
import { brl, duration } from '@/lib/format';
import type { AvailabilitySlot, Barber, Service } from '@/types/booking';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    services: Service[];
    barbers: Barber[];
}

export default function Wizard({ services, barbers }: Props) {
    const [step, setStep] = useState(1);
    const [service, setService] = useState<Service | null>(null);
    const [barberId, setBarberId] = useState<number | null>(null);
    const [date, setDate] = useState<string | null>(null);
    const [slot, setSlot] = useState<AvailabilitySlot | null>(null);
    const [form, setForm] = useState<CustomerForm>({ name: '', phone: '', note: '', payment_method: 'pix' });
    const [errors, setErrors] = useState<Partial<Record<keyof CustomerForm, string>>>({});
    const [submitting, setSubmitting] = useState(false);
    const [failure, setFailure] = useState<string | null>(null);

    const availability = useAvailability(step === 3 ? service : null, barberId, date);

    const back = () => {
        setStep((current) => Math.max(1, current - 1));
    };

    const pickService = (picked: Service) => {
        setService(picked);
        setDate(null);
        setSlot(null);
        setStep(2);
    };

    const pickBarber = (picked: number | null) => {
        setBarberId(picked);
        setDate(null);
        setSlot(null);
        setStep(3);
    };

    /** Passo 04: confirma o horário; o servidor devolve a confirmação. */
    const submit = async () => {
        if (!service || !slot || submitting) {
            return;
        }

        setSubmitting(true);
        setErrors({});
        setFailure(null);

        try {
            const response = await fetch('/agendamentos', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
                },
                body: JSON.stringify({
                    service_id: service.id,
                    barber_id: barberId,
                    starts_at: slot.starts_at,
                    ...form,
                }),
            });

            const payload = await response.json();

            if (response.status === 201) {
                router.visit(payload.redirect);

                return;
            }

            if (response.status === 422) {
                setErrors(Object.fromEntries(Object.entries(payload.errors ?? {}).map(([key, list]) => [key, (list as string[])[0]])));

                return;
            }

            // 409 = horário tomado no meio do caminho: volta para a grade
            if (response.status === 409) {
                setSlot(null);
                setStep(3);
            }

            setFailure(payload.message ?? 'Não conseguimos concluir agora. Tente de novo.');
        } catch {
            setFailure('Falha de conexão. Tente de novo.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <Head title="Agendar" />

            {step === 1 && (
                <WizardShell step={1} title="O que vamos fazer hoje?" subtitle="Escolha o serviço. Duração e preço já entram na reserva.">
                    <StepService services={services} selected={service} onSelect={pickService} />
                </WizardShell>
            )}

            {step === 2 && (
                <WizardShell step={2} title="Com quem?" subtitle="Sem preferência? A gente encaixa com quem estiver livre." onBack={back}>
                    <StepBarber barbers={barbers} selected={barberId} onSelect={pickBarber} />
                </WizardShell>
            )}

            {step === 3 && (
                <WizardShell
                    step={3}
                    title="Quando fica bom?"
                    subtitle={service ? `${service.name} · ${duration(service.duration_min)} · ${brl(service.price_cents)}` : undefined}
                    onBack={back}
                    footer={
                        <Button className="w-full" size="lg" disabled={!slot} onClick={() => setStep(4)}>
                            {slot ? `Continuar · ${slot.label}` : 'Escolha um horário'}
                        </Button>
                    }
                >
                    <StepSlot
                        days={availability.days}
                        slots={availability.slots}
                        date={date}
                        slot={slot}
                        loadingDays={availability.loadingDays}
                        loadingSlots={availability.loadingSlots}
                        showBarber={barberId === null}
                        onPickDate={(picked) => {
                            setDate(picked);
                            setSlot(null);
                        }}
                        onPickSlot={setSlot}
                    />
                    <p className="text-muted-foreground mt-6 text-xs">
                        O preço é uma referência do serviço. O método de pagamento será confirmado no atendimento.
                    </p>
                </WizardShell>
            )}

            {step === 4 && service && slot && date && (
                <WizardShell
                    step={4}
                    title="Seus dados"
                    subtitle="Só o necessário para confirmar e avisar você."
                    onBack={back}
                    footer={
                        <div className="space-y-2">
                            {failure && <p className="text-destructive text-center text-xs">{failure}</p>}
                            <Button className="w-full" size="lg" disabled={submitting} onClick={submit}>
                                {submitting ? 'Confirmando…' : `Confirmar agendamento · ${brl(service.price_cents)}`}
                            </Button>
                        </div>
                    }
                >
                    <StepCustomer
                        form={form}
                        errors={errors}
                        service={service}
                        slot={slot}
                        date={date}
                        onChange={(patch) => setForm((current) => ({ ...current, ...patch }))}
                    />
                </WizardShell>
            )}
        </>
    );
}
