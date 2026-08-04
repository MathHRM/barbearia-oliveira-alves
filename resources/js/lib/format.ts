export function brl(cents: number): string {
    return (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function duration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return rest === 0 ? `${hours}h` : `${hours}h${String(rest).padStart(2, '0')}`;
}

/** `2026-09-03` no fuso da barbearia — evita o Date() interpretar como UTC e voltar um dia. */
function fromIsoDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function isoToday(): string {
    const now = new Date();

    return [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
}

/** "Hoje" / "Amanhã" / "qui, 03 set" — o rótulo dos dias no passo 03. */
export function dayLabel(date: string): string {
    const today = isoToday();

    if (date === today) {
        return 'Hoje';
    }

    const tomorrow = new Date(fromIsoDate(today));
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (date === isoDate(tomorrow)) {
        return 'Amanhã';
    }

    return fromIsoDate(date).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: 'short' }).replace('.', '');
}

export function isoDate(date: Date): string {
    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
}

/** Partes curtas para o chip de dia: "qui" + "03" + "set". */
export function dayParts(date: string): { weekday: string; day: string; month: string } {
    const parsed = fromIsoDate(date);

    return {
        weekday: parsed.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', ''),
        day: String(parsed.getDate()).padStart(2, '0'),
        month: parsed.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', ''),
    };
}

/** "Hoje" / "Amanhã" / "Ontem" / "05/09/2026" — `today` vem do servidor, no fuso da barbearia. */
export function relativeDay(date: string, today: string): string {
    const shifted = (days: number) => {
        const moment = fromIsoDate(today);
        moment.setDate(moment.getDate() + days);

        return isoDate(moment);
    };

    if (date === today) {
        return 'Hoje';
    }

    if (date === shifted(1)) {
        return 'Amanhã';
    }

    if (date === shifted(-1)) {
        return 'Ontem';
    }

    return fromIsoDate(date).toLocaleDateString('pt-BR');
}

export function longDate(date: string): string {
    return fromIsoDate(date).toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' });
}

/** Máscara progressiva do WhatsApp: (11) 98888-7777 */
export function maskPhone(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 6) {
        return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    const split = digits.length > 10 ? 7 : 6;

    return `(${digits.slice(0, 2)}) ${digits.slice(2, split)}-${digits.slice(split)}`;
}

/** Máscara progressiva do CPF: 390.533.447-05 */
export function maskCpf(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
}
