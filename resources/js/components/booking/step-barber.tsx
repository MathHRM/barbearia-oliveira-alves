import { OptionCard } from '@/components/booking/option-card';
import type { Barber } from '@/types/booking';
import { Users } from 'lucide-react';

interface Props {
    barbers: Barber[];
    selected: number | null;
    onSelect: (barberId: number | null) => void;
}

export function StepBarber({ barbers, selected, onSelect }: Props) {
    return (
        <div className="space-y-3">
            <OptionCard selected={selected === null} onClick={() => onSelect(null)}>
                <div className="flex items-center gap-3">
                    <span className="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-full">
                        <Users className="size-5" />
                    </span>
                    <div>
                        <p className="font-display text-base font-semibold">Tanto faz</p>
                        <p className="text-muted-foreground text-sm">Quem estiver livre primeiro</p>
                    </div>
                </div>
            </OptionCard>

            {barbers.map((barber) => (
                <OptionCard key={barber.id} selected={selected === barber.id} onClick={() => onSelect(barber.id)}>
                    <div className="flex items-center gap-3">
                        <span className="font-display border-border bg-accent flex size-11 shrink-0 items-center justify-center rounded-full border text-sm font-semibold">
                            {barber.initials}
                        </span>
                        <div className="min-w-0">
                            <p className="font-display text-base font-semibold">{barber.name}</p>
                            {barber.headline && <p className="text-muted-foreground truncate text-sm">{barber.headline}</p>}
                        </div>
                    </div>
                </OptionCard>
            ))}
        </div>
    );
}
