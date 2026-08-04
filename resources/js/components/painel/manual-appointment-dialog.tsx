import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { brl, maskPhone } from '@/lib/format';
import type { PainelBarber, PainelService } from '@/types/painel';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

interface Props {
    date: string;
    services: PainelService[];
    barbers: PainelBarber[];
    canPickBarber: boolean;
}

/** Lançamento de balcão: entra confirmado, sem gateway — o caixa resolve o pagamento. */
export function ManualAppointmentDialog({ date, services, barbers, canPickBarber }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        service_id: services[0]?.id ?? 0,
        barber_id: barbers[0]?.id ?? 0,
        date,
        time: '',
        name: '',
        phone: '',
        note: '',
    });

    const submit = () => {
        post('/painel/agendamentos', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    setData('date', date);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="size-4" /> Balcão
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Agendamento de balcão</DialogTitle>
                    <DialogDescription>Cliente que chegou ou ligou. Já entra confirmado.</DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Serviço</Label>
                        <select
                            value={data.service_id}
                            onChange={(event) => setData('service_id', Number(event.target.value))}
                            className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                        >
                            {services.map((service) => (
                                <option key={service.id} value={service.id}>
                                    {service.name} · {service.duration_min} min · {brl(service.price_cents)}
                                </option>
                            ))}
                        </select>
                    </div>

                    {canPickBarber && (
                        <div className="space-y-1.5">
                            <Label>Profissional</Label>
                            <select
                                value={data.barber_id}
                                onChange={(event) => setData('barber_id', Number(event.target.value))}
                                className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                            >
                                {barbers.map((barber) => (
                                    <option key={barber.id} value={barber.id}>
                                        {barber.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="manual-date">Dia</Label>
                            <Input id="manual-date" type="date" value={data.date} onChange={(event) => setData('date', event.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="manual-time">Hora</Label>
                            <Input id="manual-time" type="time" value={data.time} onChange={(event) => setData('time', event.target.value)} />
                            {errors.time && <p className="text-destructive text-xs">{errors.time}</p>}
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="manual-name">Cliente</Label>
                        <Input id="manual-name" value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="Nome" />
                        {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="manual-phone">WhatsApp</Label>
                        <Input
                            id="manual-phone"
                            value={data.phone}
                            inputMode="numeric"
                            onChange={(event) => setData('phone', maskPhone(event.target.value))}
                            placeholder="(31) 98888-7777"
                        />
                        {errors.phone && <p className="text-destructive text-xs">{errors.phone}</p>}
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => setOpen(false)}>
                        Voltar
                    </Button>
                    <Button disabled={processing} onClick={submit}>
                        Lançar
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
