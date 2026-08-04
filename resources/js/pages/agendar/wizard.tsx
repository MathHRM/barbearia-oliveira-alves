import { StepBarber } from '@/components/booking/step-barber';
import { StepService } from '@/components/booking/step-service';
import { StepSlot } from '@/components/booking/step-slot';
import { WizardShell } from '@/components/booking/wizard-shell';
import { Button } from '@/components/ui/button';
import { useAvailability } from '@/hooks/use-availability';
import { brl, duration } from '@/lib/format';
import type { AvailabilitySlot, Barber, Service, Shop } from '@/types/booking';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    services: Service[];
    barbers: Barber[];
    shop: Shop;
}

export default function Wizard({ services, barbers, shop }: Props) {
    const [step, setStep] = useState(1);
    const [service, setService] = useState<Service | null>(null);
    const [barberId, setBarberId] = useState<number | null>(null);
    const [date, setDate] = useState<string | null>(null);
    const [slot, setSlot] = useState<AvailabilitySlot | null>(null);

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
                        <Button className="w-full" size="lg" disabled={!slot}>
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
                        O horário fica reservado por {shop.reservation_ttl_min} minutos enquanto você paga.
                    </p>
                </WizardShell>
            )}
        </>
    );
}
