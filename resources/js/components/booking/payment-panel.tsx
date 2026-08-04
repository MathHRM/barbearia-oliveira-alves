import { Button } from '@/components/ui/button';
import type { TrackedAppointment } from '@/pages/agendar/acompanhamento';
import { Check, Copy, ExternalLink, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Passo 05: Pix resolvido dentro da tela, cartão sai para a fatura hospedada do Asaas. */
export function PaymentPanel({ appointment }: { appointment: TrackedAppointment }) {
    const [copied, setCopied] = useState(false);
    const remaining = useCountdown(appointment.reserved_until);
    const payment = appointment.payment;

    if (!payment) {
        return <p className="text-muted-foreground text-sm">Cobrança indisponível. Fale com a barbearia.</p>;
    }

    const copy = async () => {
        if (!payment.pix_payload) {
            return;
        }

        await navigator.clipboard.writeText(payment.pix_payload);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="space-y-4">
            {remaining !== null && (
                <p className="text-muted-foreground text-center text-sm">
                    Horário preso por mais <span className="tabular text-foreground font-semibold">{remaining}</span>
                </p>
            )}

            {payment.billing_type === 'PIX' ? (
                <div className="border-border bg-card space-y-4 rounded-[1.125rem] border p-4">
                    {payment.pix_qr_base64 && (
                        <img
                            src={`data:image/png;base64,${payment.pix_qr_base64}`}
                            alt="QR Code do Pix"
                            className="mx-auto w-48 rounded-lg bg-white p-2"
                        />
                    )}

                    <Button variant="outline" className="w-full" size="lg" onClick={copy}>
                        {copied ? <Check className="text-success size-4" /> : <Copy className="size-4" />}
                        {copied ? 'Código copiado' : 'Copiar código Pix'}
                    </Button>
                </div>
            ) : (
                <a href={payment.invoice_url ?? '#'} target="_blank" rel="noreferrer">
                    <Button className="w-full" size="lg">
                        <ExternalLink className="size-4" /> Pagar com cartão
                    </Button>
                </a>
            )}

            <p className="text-muted-foreground flex items-center justify-center gap-2 text-xs">
                <Loader2 className="size-3 animate-spin" /> Confirmamos automaticamente assim que o pagamento cair.
            </p>
        </div>
    );
}

/** Conta o tempo que falta da reserva; devolve null quando não há prazo ou já venceu. */
function useCountdown(until: string | null): string | null {
    const [left, setLeft] = useState(() => remainingSeconds(until));

    useEffect(() => {
        if (until === null) {
            return;
        }

        const timer = window.setInterval(() => setLeft(remainingSeconds(until)), 1000);

        return () => window.clearInterval(timer);
    }, [until]);

    if (left === null || left <= 0) {
        return null;
    }

    return `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
}

function remainingSeconds(until: string | null): number | null {
    return until === null ? null : Math.floor((new Date(until).getTime() - Date.now()) / 1000);
}
