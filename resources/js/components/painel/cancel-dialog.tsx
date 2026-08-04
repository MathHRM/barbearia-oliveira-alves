import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AgendaRow } from '@/types/painel';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

/** Cancelamento pelo painel: o estorno é escolha explícita de quem cancela. */
export function CancelDialog({ row, onClose }: { row: AgendaRow | null; onClose: () => void }) {
    const { data, setData, post, processing, reset } = useForm<{ reason: string; refund: boolean }>({ reason: '', refund: false });

    useEffect(() => {
        if (row) {
            reset();
            setData({ reason: '', refund: row.payment?.refundable ?? false });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row?.id]);

    if (!row) {
        return null;
    }

    const submit = () => {
        post(`/painel/agendamentos/${row.id}/cancelar`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancelar {row.code}</DialogTitle>
                    <DialogDescription>
                        {row.customer.name} · {row.starts_at} · {row.service}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="reason">Motivo</Label>
                        <Input
                            id="reason"
                            value={data.reason}
                            onChange={(event) => setData('reason', event.target.value)}
                            placeholder="Cliente avisou pelo WhatsApp"
                        />
                    </div>

                    {row.payment?.refundable ? (
                        <label className="flex items-start gap-3 text-sm">
                            <Checkbox checked={data.refund} onClick={() => setData('refund', !data.refund)} />
                            <span>
                                Estornar {row.payment.billing_type === 'PIX' ? 'o Pix' : 'o cartão'} integralmente
                                <span className="text-muted-foreground block text-xs">O Asaas devolve o valor cheio para o cliente.</span>
                            </span>
                        </label>
                    ) : (
                        <p className="text-muted-foreground text-xs">Sem pagamento confirmado — nada a estornar.</p>
                    )}
                </div>

                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={onClose}>
                        Voltar
                    </Button>
                    <Button variant="destructive" disabled={processing} onClick={submit}>
                        Cancelar agendamento
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
