import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AgendaRow } from '@/types/painel';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

/** Cancelamento pelo painel, sem fluxo financeiro. */
export function CancelDialog({ row, onClose }: { row: AgendaRow | null; onClose: () => void }) {
    const { data, setData, post, processing, reset } = useForm<{ reason: string }>({ reason: '' });

    useEffect(() => {
        if (row) {
            reset();
            setData({ reason: '' });
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

                    <p className="text-muted-foreground text-xs">O método informado é apenas uma estimativa e não gera estorno.</p>
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
