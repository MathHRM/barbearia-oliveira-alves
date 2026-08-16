import { BrandMark } from '@/components/booking/brand-mark';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ChartNoAxesColumn, Clock, LogOut, Menu, Scissors, UserRound, Users, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

interface NavItem {
    href: string;
    label: string;
    icon: typeof CalendarDays;
    ownerOnly?: boolean;
}

const NAV: NavItem[] = [
    { href: '/painel/agenda', label: 'Agenda', icon: CalendarDays },
    { href: '/painel/dashboard', label: 'Dashboard', icon: ChartNoAxesColumn, ownerOnly: true },
    { href: '/painel/clientes', label: 'Clientes', icon: Users },
    { href: '/painel/servicos', label: 'Serviços', icon: Scissors, ownerOnly: true },
    { href: '/painel/horarios', label: 'Horários', icon: Clock },
    { href: '/painel/barbeiros', label: 'Barbeiros', icon: UserRound, ownerOnly: true },
];

interface Props {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    children: ReactNode;
}

export function PainelLayout({ title, subtitle, actions, children }: Props) {
    const page = usePage<{
        auth: { user: { name: string; email: string } | null; is_owner: boolean };
        flash: { success: string | null; error: string | null };
    }>();
    const { auth } = page.props;
    const items = NAV.filter((item) => !item.ownerOnly || auth.is_owner);
    const current = page.url.split('?')[0];
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    useEffect(() => {
        if (!mobileMenuOpen) {
            return;
        }

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMobileMenuOpen(false);
            }
        };

        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, [mobileMenuOpen]);

    return (
        <div className="bg-background text-foreground min-h-svh md:flex md:items-start">
            {/* em tela grande a sidebar fica parada e só o conteúdo rola */}
            <aside className="border-border/80 bg-card hidden md:sticky md:top-0 md:flex md:h-svh md:w-60 md:shrink-0 md:flex-col md:overflow-y-auto md:border-r">
                <div className="border-border/80 flex items-center justify-between border-b px-4 py-4 md:justify-center">
                    <BrandMark className="scale-90 md:scale-100" />
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="text-muted-foreground hover:text-foreground md:hidden"
                        aria-label="Sair"
                    >
                        <LogOut className="size-4" />
                    </button>
                </div>

                <nav className="flex gap-1 overflow-x-auto px-3 py-3 md:flex-col md:overflow-visible">
                    {items.map((item) => {
                        const active = current.startsWith(item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm transition',
                                    active ? 'bg-primary/10 text-primary font-medium' : 'text-muted-foreground hover:text-foreground hover:bg-accent',
                                )}
                            >
                                <item.icon className="size-4" />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-border/80 mt-auto hidden border-t px-4 py-4 md:block">
                    <p className="truncate text-sm font-medium">{auth.user?.name}</p>
                    <p className="text-muted-foreground truncate text-xs">{auth.is_owner ? 'Dono' : 'Barbeiro'}</p>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                        className="text-muted-foreground hover:text-destructive mt-3 flex items-center gap-2 text-xs"
                    >
                        <LogOut className="size-3.5" /> Sair
                    </button>
                </div>
            </aside>

            <main className="w-full min-w-0 flex-1 overflow-x-hidden">
                <header className="border-border/80 flex flex-wrap items-center gap-3 border-b px-4 py-4 md:px-8 md:py-5">
                    <div className="flex w-full items-center justify-between md:hidden">
                        <BrandMark className="scale-90" />
                        <button
                            type="button"
                            aria-controls="painel-mobile-menu"
                            aria-expanded={mobileMenuOpen}
                            aria-label={mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'}
                            onClick={() => setMobileMenuOpen((open) => !open)}
                            className="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-lg p-2 transition focus-visible:ring-2 focus-visible:outline-none"
                        >
                            {mobileMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                        </button>
                    </div>
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl leading-tight md:text-2xl">{title}</h1>
                        {subtitle && <p className="text-muted-foreground mt-1 text-sm">{subtitle}</p>}
                    </div>
                    {actions && <div className="w-full shrink-0 md:w-auto">{actions}</div>}
                </header>

                {mobileMenuOpen && (
                    <>
                        <button
                            type="button"
                            aria-label="Fechar menu"
                            onClick={() => setMobileMenuOpen(false)}
                            className="fixed inset-0 z-30 bg-black/50 md:hidden"
                        />
                        <nav
                            id="painel-mobile-menu"
                            aria-label="Navegação principal"
                            className="border-border bg-card fixed inset-x-4 top-24 z-40 rounded-2xl border p-2 shadow-2xl md:hidden"
                        >
                            {items.map((item) => {
                                const active = current.startsWith(item.href);

                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={cn(
                                            'flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition',
                                            active
                                                ? 'bg-primary/10 text-primary border-primary/30 border-l-2 font-medium'
                                                : 'text-muted-foreground hover:text-foreground hover:bg-accent',
                                        )}
                                    >
                                        <item.icon className="size-4" />
                                        {item.label}
                                    </Link>
                                );
                            })}
                            <div className="border-border/80 mt-2 border-t px-3 py-3">
                                <p className="truncate text-sm font-medium">{auth.user?.name}</p>
                                <button
                                    type="button"
                                    onClick={() => router.post('/logout')}
                                    className="text-muted-foreground hover:text-destructive mt-2 flex min-h-10 items-center gap-2 text-sm"
                                >
                                    <LogOut className="size-4" /> Sair
                                </button>
                            </div>
                        </nav>
                    </>
                )}

                <div className="px-4 py-5 md:px-8 md:py-6">{children}</div>
            </main>

            <Toast message={page.props.flash?.error ?? page.props.flash?.success ?? null} tone={page.props.flash?.error ? 'danger' : 'success'} />
        </div>
    );
}

/** Confirmação curta das ações do painel; some sozinha em 3s. */
function Toast({ message, tone }: { message: string | null; tone: 'success' | 'danger' }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!message) {
            return;
        }

        setVisible(true);
        const timer = window.setTimeout(() => setVisible(false), 3000);

        return () => window.clearTimeout(timer);
    }, [message]);

    if (!message || !visible) {
        return null;
    }

    return (
        <div
            className={cn(
                'fixed right-4 bottom-4 z-50 rounded-xl border px-4 py-2 text-sm shadow-lg',
                tone === 'danger' ? 'border-destructive/40 bg-destructive/10 text-destructive' : 'border-success/40 bg-success/10 text-success',
            )}
        >
            {message}
        </div>
    );
}
