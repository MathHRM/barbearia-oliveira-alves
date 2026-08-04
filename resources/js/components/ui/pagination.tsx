import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

/** Rodapé de tabela: contagem em português + páginas numeradas. `label` é o plural ("clientes"). */
export function Pagination<T>({ page, label }: { page: Paginated<T>; label: string }) {
    if (page.total === 0) {
        return null;
    }

    const pages = page.links.slice(1, -1);

    return (
        <nav className="border-border bg-card mt-4 flex flex-wrap items-center justify-between gap-4 rounded-[1.125rem] border px-5 py-4">
            <p className="text-muted-foreground text-sm">
                Mostrando <span className="text-foreground tabular font-medium">{page.from ?? 0}</span>–
                <span className="text-foreground tabular font-medium">{page.to ?? 0}</span> de{' '}
                <span className="text-foreground tabular font-medium">{page.total}</span> {label}
            </p>

            {page.last_page > 1 && (
                <div className="flex flex-wrap items-center gap-1">
                    <PageLink url={page.prev_page_url}>
                        <ChevronLeft className="size-4" /> Anterior
                    </PageLink>

                    <div className="hidden items-center gap-1 sm:flex">
                        {pages.map((link, index) => (
                            <PageLink key={index} url={link.url} active={link.active}>
                                {link.label}
                            </PageLink>
                        ))}
                    </div>

                    <span className="text-muted-foreground tabular px-2 text-sm sm:hidden">
                        {page.current_page} de {page.last_page}
                    </span>

                    <PageLink url={page.next_page_url}>
                        Próxima <ChevronRight className="size-4" />
                    </PageLink>
                </div>
            )}
        </nav>
    );
}

function PageLink({ url, active, children }: { url: string | null; active?: boolean; children: ReactNode }) {
    const classes = cn(
        'flex h-9 min-w-9 items-center justify-center gap-1 rounded-lg border px-3 text-sm transition',
        active
            ? 'border-primary/40 bg-primary/10 text-primary font-medium'
            : 'border-border text-muted-foreground hover:text-foreground hover:bg-accent',
    );

    if (url === null) {
        return <span className={cn(classes, 'opacity-40')}>{children}</span>;
    }

    return (
        <Link href={url} preserveScroll preserveState className={classes}>
            {children}
        </Link>
    );
}
