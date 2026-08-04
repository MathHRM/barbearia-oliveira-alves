import { OptionCard } from '@/components/booking/option-card';
import { brl, duration } from '@/lib/format';
import type { Service } from '@/types/booking';

interface Props {
    services: Service[];
    selected: Service | null;
    onSelect: (service: Service) => void;
}

export function StepService({ services, selected, onSelect }: Props) {
    return (
        <div className="space-y-3">
            {services.map((service) => (
                <OptionCard key={service.id} selected={selected?.id === service.id} onClick={() => onSelect(service)}>
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="font-display text-base font-semibold">{service.name}</span>
                        <span className="tabular text-primary text-sm font-semibold">{brl(service.price_cents)}</span>
                    </div>
                    {service.description && <p className="text-muted-foreground mt-1 text-sm">{service.description}</p>}
                    <p className="tabular text-muted-foreground mt-2 text-xs">{duration(service.duration_min)}</p>
                </OptionCard>
            ))}
        </div>
    );
}
