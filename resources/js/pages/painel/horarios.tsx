import { Button } from '@/components/ui/button';
import { CheckboxField } from '@/components/ui/checkbox-field';
import { TimeInput } from '@/components/ui/date-time-input';
import { PainelLayout } from '@/layouts/painel-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Hour {
    weekday: number;
    starts_at: string;
    ends_at: string;
}

interface BarberHours {
    id: number;
    name: string;
    active: boolean;
    hours: Hour[];
}

interface Props {
    weekdays: string[];
    barbers: BarberHours[];
}

export default function Horarios({ weekdays, barbers }: Props) {
    return (
        <PainelLayout title="Horários" subtitle="A grade semanal é a base da agenda pública. Dia sem faixa é folga.">
            <Head title="Horários" />

            <div className="space-y-6">
                {barbers.map((barber) => (
                    <BarberGrid key={barber.id} barber={barber} weekdays={weekdays} />
                ))}
            </div>
        </PainelLayout>
    );
}

function BarberGrid({ barber, weekdays }: { barber: BarberHours; weekdays: string[] }) {
    const [hours, setHours] = useState<Record<number, { starts_at: string; ends_at: string } | null>>(() =>
        Object.fromEntries(
            weekdays.map((_, weekday) => {
                const found = barber.hours.find((hour) => hour.weekday === weekday);

                return [weekday, found ? { starts_at: found.starts_at, ends_at: found.ends_at } : null];
            }),
        ),
    );
    const [saving, setSaving] = useState(false);

    const toggle = (weekday: number) => {
        setHours((current) => ({
            ...current,
            [weekday]: current[weekday] ? null : { starts_at: '09:00', ends_at: '18:00' },
        }));
    };

    const save = () => {
        setSaving(true);

        router.put(
            `/painel/horarios/${barber.id}`,
            {
                hours: Object.entries(hours)
                    .filter(([, value]) => value !== null)
                    .map(([weekday, value]) => ({ weekday: Number(weekday), ...value! })),
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <section className="border-border bg-card rounded-[1.125rem] border p-5">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <p className="font-display text-base font-semibold">{barber.name}</p>
                    {!barber.active && <p className="text-muted-foreground text-xs">inativo — não aparece no site</p>}
                </div>
                <Button size="sm" disabled={saving} onClick={save}>
                    Salvar grade
                </Button>
            </div>

            <ul className="space-y-2">
                {weekdays.map((label, weekday) => {
                    const value = hours[weekday];

                    return (
                        <li key={weekday} className="flex flex-wrap items-center gap-3">
                            <CheckboxField className="w-32" checked={value !== null} onChange={() => toggle(weekday)} label={label} />

                            {value ? (
                                <div className="flex items-center gap-2">
                                    <TimeInput
                                        className="w-32"
                                        value={value.starts_at}
                                        onChange={(time) => setHours((current) => ({ ...current, [weekday]: { ...value, starts_at: time } }))}
                                        aria-label={`${label}: início`}
                                    />
                                    <span className="text-muted-foreground text-sm">até</span>
                                    <TimeInput
                                        className="w-32"
                                        value={value.ends_at}
                                        onChange={(time) => setHours((current) => ({ ...current, [weekday]: { ...value, ends_at: time } }))}
                                        aria-label={`${label}: fim`}
                                    />
                                </div>
                            ) : (
                                <span className="text-muted-foreground text-sm">Folga</span>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
