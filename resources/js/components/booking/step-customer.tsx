import { OptionCard } from '@/components/booking/option-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brl, duration, longDate, maskCpf, maskPhone } from '@/lib/format';
import type { AvailabilitySlot, Service, Shop } from '@/types/booking';
import { CreditCard, QrCode } from 'lucide-react';

export interface CustomerForm {
    name: string;
    phone: string;
    document: string;
    email: string;
    note: string;
    billing_type: 'PIX' | 'CREDIT_CARD';
}

interface Props {
    form: CustomerForm;
    errors: Partial<Record<keyof CustomerForm, string>>;
    service: Service;
    slot: AvailabilitySlot;
    date: string;
    shop: Shop;
    onChange: (patch: Partial<CustomerForm>) => void;
}

export function StepCustomer({ form, errors, service, slot, date, shop, onChange }: Props) {
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

                <Field label="CPF" error={errors.document} hint="Exigido pelo pagamento.">
                    <Input
                        value={form.document}
                        onChange={(event) => onChange({ document: maskCpf(event.target.value) })}
                        inputMode="numeric"
                        placeholder="000.000.000-00"
                    />
                </Field>

                <Field label="E-mail" error={errors.email} hint="Para receber a confirmação.">
                    <Input
                        type="email"
                        value={form.email}
                        onChange={(event) => onChange({ email: event.target.value })}
                        placeholder="voce@email.com"
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
                <p className="eyebrow">Como você prefere pagar</p>

                <OptionCard selected={form.billing_type === 'PIX'} onClick={() => onChange({ billing_type: 'PIX' })}>
                    <div className="flex items-center gap-3">
                        <QrCode className="text-primary size-5" />
                        <div>
                            <p className="font-display text-base font-semibold">Pix</p>
                            <p className="text-muted-foreground text-sm">Confirma em segundos</p>
                        </div>
                    </div>
                </OptionCard>

                <OptionCard selected={form.billing_type === 'CREDIT_CARD'} onClick={() => onChange({ billing_type: 'CREDIT_CARD' })}>
                    <div className="flex items-center gap-3">
                        <CreditCard className="text-primary size-5" />
                        <div>
                            <p className="font-display text-base font-semibold">Cartão de crédito</p>
                            <p className="text-muted-foreground text-sm">Você paga em uma página segura do Asaas</p>
                        </div>
                    </div>
                </OptionCard>
            </div>

            <p className="text-muted-foreground text-xs">
                O horário só é confirmado depois do pagamento aprovado. Cancelamento com estorno integral até {shop.cancel_window_hours}h antes do
                atendimento.
            </p>
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
