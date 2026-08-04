import { OptionCard } from '@/components/booking/option-card';
import { Skeleton } from '@/components/ui/skeleton';
import { dayParts, longDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { AvailabilityDay, AvailabilitySlot } from '@/types/booking';
import { CalendarX2 } from 'lucide-react';

interface Props {
    days: AvailabilityDay[];
    slots: AvailabilitySlot[];
    date: string | null;
    slot: AvailabilitySlot | null;
    loadingDays: boolean;
    loadingSlots: boolean;
    showBarber: boolean;
    onPickDate: (date: string) => void;
    onPickSlot: (slot: AvailabilitySlot) => void;
}

export function StepSlot({ days, slots, date, slot, loadingDays, loadingSlots, showBarber, onPickDate, onPickSlot }: Props) {
    if (loadingDays) {
        return <Skeleton className="h-24 w-full rounded-[1.125rem]" />;
    }

    if (days.length === 0) {
        return <EmptyState message="Nenhum horário livre nos próximos dias. Tente outro profissional ou serviço." />;
    }

    return (
        <div className="space-y-6">
            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                {days.map((day) => {
                    const parts = dayParts(day.date);
                    const active = day.date === date;

                    return (
                        <button
                            key={day.date}
                            type="button"
                            onClick={() => onPickDate(day.date)}
                            aria-pressed={active}
                            className={cn(
                                'flex w-16 shrink-0 flex-col items-center rounded-2xl border px-2 py-3 transition',
                                active ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card hover:border-primary/50',
                            )}
                        >
                            <span className="text-[0.6875rem] tracking-wide uppercase">{parts.weekday}</span>
                            <span className="tabular font-display text-lg leading-tight font-semibold">{parts.day}</span>
                            <span className="text-muted-foreground text-[0.6875rem]">{parts.month}</span>
                            <span className="tabular text-muted-foreground mt-1 text-[0.625rem]">{day.count} livres</span>
                        </button>
                    );
                })}
            </div>

            {date && (
                <div className="space-y-3">
                    <p className="eyebrow">{longDate(date)}</p>

                    {loadingSlots ? (
                        <div className="space-y-3">
                            {[0, 1, 2].map((index) => (
                                <Skeleton key={index} className="h-[4.5rem] w-full rounded-[1.125rem]" />
                            ))}
                        </div>
                    ) : slots.length === 0 ? (
                        <EmptyState message="Esse dia acabou de lotar. Escolha outro." />
                    ) : (
                        slots.map((option) => (
                            <OptionCard key={option.starts_at} selected={slot?.starts_at === option.starts_at} onClick={() => onPickSlot(option)}>
                                <div className="flex items-center gap-4">
                                    <span className="tabular font-display text-xl font-semibold">{option.label}</span>
                                    {showBarber && (
                                        <span className="text-muted-foreground truncate text-sm">
                                            {option.barbers.length === 1
                                                ? `com ${option.barbers[0].name}`
                                                : `${option.barbers.length} profissionais livres`}
                                        </span>
                                    )}
                                </div>
                            </OptionCard>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="border-border flex flex-col items-center gap-3 rounded-[1.125rem] border border-dashed p-8 text-center">
            <CalendarX2 className="text-muted-foreground size-6" />
            <p className="text-muted-foreground text-sm">{message}</p>
        </div>
    );
}
