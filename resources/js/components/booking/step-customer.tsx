import { OptionCard } from '@/components/booking/option-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brl, duration, longDate, maskPhone } from '@/lib/format';
import type { AvailabilitySlot, Service } from '@/types/booking';
import { Banknote, CreditCard, QrCode } from 'lucide-react';

export interface CustomerForm {
    name: string;
    phone: string;
    note: string;
    payment_method: 'pix' | 'card' | 'cash';
}

interface Props {
    form: CustomerForm;
    errors: Partial<Record<keyof CustomerForm, string>>;
    service: Service;
    slot: AvailabilitySlot;
    date: string;
    onChange: (patch: Partial<CustomerForm>) => void;
}

export function StepCustomer({ form, errors, service, slot, date, onChange }: Props) {
    return (
        <div className="space-y-6">
            <div className="border-border bg-card rounded-[1.125rem] border p-4">
                <p className="eyebrow">Resumo</p>
                <p className="font-display mt-2 text-base font-semibold">{service.name}</p>
                <p className="text-muted-foreground mt-1 text-sm">
                    {longDate(date)} · <span className="tabular">{slot.label}</span> · {duration(service.duration_min)}
                </p>
                <p className="tabular text-primary mt-2 text-lg font-semibold">{brl(service.price_cents)}</p>
            </div>

            <div className="space-y-4">
                <Field label="Nome" error={errors.name}>
                    <Input value={form.name} onChange={(event) => onChange({ name: event.target.value })} placeholder="Como te chamamos" />
                </Field>

                <Field label="WhatsApp" error={errors.phone}>
                    <Input
                        value={form.phone}
                        onChange={(event) => onChange({ phone: maskPhone(event.target.value) })}
                        inputMode="numeric"
                        placeholder="(11) 98888-7777"
                    />
                </Field>

                <Field label="Observação" error={errors.note} hint="Opcional.">
                    <Input
                        value={form.note}
                        onChange={(event) => onChange({ note: event.target.value })}
                        placeholder="Algo que o barbeiro precisa saber"
                    />
                </Field>
            </div>

            <div className="space-y-3">
                <p className="eyebrow">Como você pretende pagar</p>

                <OptionCard selected={form.payment_method === 'pix'} onClick={() => onChange({ payment_method: 'pix' })}>
                    <div className="flex items-center gap-3">
                        <QrCode className="text-primary size-5" />
                        <div>
                            <p className="font-display text-base font-semibold">Pix</p>
                            <p className="text-muted-foreground text-sm">Estimativa para o atendimento</p>
                        </div>
                    </div>
                </OptionCard>

                <OptionCard selected={form.payment_method === 'card'} onClick={() => onChange({ payment_method: 'card' })}>
                    <div className="flex items-center gap-3">
                        <CreditCard className="text-primary size-5" />
                        <div>
                            <p className="font-display text-base font-semibold">Cartão de crédito</p>
                            <p className="text-muted-foreground text-sm">Estimativa para o atendimento</p>
                        </div>
                    </div>
                </OptionCard>
                <OptionCard selected={form.payment_method === 'cash'} onClick={() => onChange({ payment_method: 'cash' })}>
                    <div className="flex items-center gap-3">
                        <Banknote className="text-primary size-5" />
                        <div>
                            <p className="font-display text-base font-semibold">Dinheiro</p>
                            <p className="text-muted-foreground text-sm">Estimativa para o atendimento</p>
                        </div>
                    </div>
                </OptionCard>
            </div>

            <p className="text-muted-foreground text-xs">O agendamento é confirmado agora. O preço exibido é apenas referencial.</p>
        </div>
    );
}

function Field({ label, error, hint, children }: { label: string; error?: string; hint?: string; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5">
            <Label className="text-sm">{label}</Label>
            {children}
            {error ? <p className="text-destructive text-xs">{error}</p> : hint ? <p className="text-muted-foreground text-xs">{hint}</p> : null}
        </div>
    );
}
