import type { AvailabilityDay, AvailabilityResponse, AvailabilitySlot, Service } from '@/types/booking';
import { useEffect, useState } from 'react';

/**
 * Busca disponibilidade no servidor. Duas fases: sem `date` traz só a contagem
 * por dia (a régua de datas); com `date` traz também os horários daquele dia.
 */
export function useAvailability(service: Service | null, barberId: number | null, date: string | null) {
    const [days, setDays] = useState<AvailabilityDay[]>([]);
    const [slots, setSlots] = useState<AvailabilitySlot[]>([]);
    const [loadingDays, setLoadingDays] = useState(false);
    const [loadingSlots, setLoadingSlots] = useState(false);

    useEffect(() => {
        if (!service) {
            return;
        }

        const params = new URLSearchParams({ service_id: String(service.id) });

        if (barberId !== null) {
            params.set('barber_id', String(barberId));
        }

        if (date) {
            params.set('date', date);
        }

        const controller = new AbortController();

        // sem data escolhida = combinação nova de serviço/barbeiro, a régua inteira recarrega
        setLoadingDays(date === null);
        setLoadingSlots(date !== null);

        fetch(`/api/availability?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => response.json() as Promise<AvailabilityResponse>)
            .then((payload) => {
                setDays(payload.days);
                setSlots(payload.slots);
            })
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) {
                    setSlots([]);
                }
            })
            .finally(() => {
                setLoadingDays(false);
                setLoadingSlots(false);
            });

        return () => controller.abort();
    }, [service, barberId, date]);

    return { days, slots, loadingDays, loadingSlots };
}
