export type StatusTone = 'brand' | 'success' | 'warning' | 'danger';

export interface AgendaCustomer {
    id: number;
    name: string;
    phone: string;
    visits: number;
}

export interface AgendaRow {
    id: number;
    code: string;
    starts_at: string;
    ends_at: string;
    status: string;
    status_label: string;
    tone: StatusTone;
    origin: string;
    service: string;
    duration_min: number;
    price_cents: number;
    barber: string;
    barber_id: number;
    note: string | null;
    customer: AgendaCustomer;
    payment: { billing_type: string; status: string; refundable: boolean } | null;
    paid: boolean;
    can_attend: boolean;
    can_no_show: boolean;
    can_cancel: boolean;
}

export interface AgendaBlock {
    id: number;
    barber: string;
    starts_at: string;
    ends_at: string;
    reason: string | null;
    created_by: string | null;
    created_at: string;
}

export type { Paginated } from './pagination';

export interface AgendaTotals {
    total: number;
    confirmed: number;
    pending: number;
    attended: number;
    canceled: number;
    free_slots: number;
    expected_cents?: number;
    received_cents?: number;
}

export interface PainelBarber {
    id: number;
    name: string;
}

export interface PainelService {
    id: number;
    name: string;
    duration_min: number;
    price_cents: number;
}
