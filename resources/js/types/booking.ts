export interface Service {
    id: number;
    name: string;
    description: string | null;
    duration_min: number;
    price_cents: number;
}

export interface Barber {
    id: number;
    name: string;
    headline: string | null;
    initials: string;
}

export interface Shop {
    name: string;
    address: string;
    timezone: string;
    cancel_window_hours: number;
    reservation_ttl_min: number;
}

/** Um dia da faixa de datas do passo 03, com quantos horários ainda tem livre. */
export interface AvailabilityDay {
    date: string;
    count: number;
}

export interface AvailabilitySlot {
    starts_at: string;
    label: string;
    barbers: { id: number; name: string }[];
}

export interface AvailabilityResponse {
    days: AvailabilityDay[];
    slots: AvailabilitySlot[];
}

/** `barberId === null` = "tanto faz", a escolha só acontece na confirmação. */
export interface BookingDraft {
    service: Service | null;
    barberId: number | null;
    date: string | null;
    slot: AvailabilitySlot | null;
}
