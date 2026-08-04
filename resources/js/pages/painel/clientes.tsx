import { Pagination } from '@/components/painel/pagination';
import { StatusBadge } from '@/components/painel/status-badge';
import { Input } from '@/components/ui/input';
import { PainelLayout } from '@/layouts/painel-layout';
import type { Paginated, StatusTone } from '@/types/painel';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface CustomerRow {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    visits: number;
    last_visit: string | null;
    situation: string;
}

const TONES: Record<string, StatusTone> = { Novo: 'warning', Ativo: 'brand', Fiel: 'success', Perdido: 'danger' };

export default function Clientes({ customers, q, churn_days }: { customers: Paginated<CustomerRow>; q: string; churn_days: number }) {
    const [term, setTerm] = useState(q);

    // busca com respiro: só consulta depois que o dedo para
    useEffect(() => {
        if (term === q) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get('/painel/clientes', term === '' ? {} : { q: term }, { preserveState: true, preserveScroll: true, replace: true });
        }, 350);

        return () => window.clearTimeout(timer);
    }, [term, q]);

    return (
        <PainelLayout title="Clientes" subtitle={`${customers.total} no total · perdido = ${churn_days}+ dias sem voltar`}>
            <Head title="Clientes" />

            <div className="relative mb-5 max-w-sm">
                <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Nome ou telefone" className="pl-9" />
            </div>

            <div className="border-border overflow-x-auto rounded-[1.125rem] border">
                <table className="w-full min-w-[40rem] text-sm">
                    <thead className="bg-card text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium">Cliente</th>
                            <th className="px-4 py-3 text-left font-medium">WhatsApp</th>
                            <th className="px-4 py-3 text-left font-medium">Último corte</th>
                            <th className="px-4 py-3 text-right font-medium">Visitas</th>
                            <th className="px-4 py-3 text-right font-medium">Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        {customers.data.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="text-muted-foreground px-4 py-10 text-center">
                                    Nenhum cliente encontrado.
                                </td>
                            </tr>
                        ) : (
                            customers.data.map((customer) => (
                                <tr key={customer.id} className="border-border/80 border-t">
                                    <td className="px-4 py-3">
                                        <p className="font-medium">{customer.name}</p>
                                        {customer.email && <p className="text-muted-foreground text-xs">{customer.email}</p>}
                                    </td>
                                    <td className="tabular px-4 py-3">{customer.phone}</td>
                                    <td className="tabular text-muted-foreground px-4 py-3">{customer.last_visit ?? '—'}</td>
                                    <td className="tabular px-4 py-3 text-right">{customer.visits}</td>
                                    <td className="px-4 py-3 text-right">
                                        <StatusBadge tone={TONES[customer.situation] ?? 'brand'}>{customer.situation}</StatusBadge>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination page={customers} label="clientes" />
        </PainelLayout>
    );
}
