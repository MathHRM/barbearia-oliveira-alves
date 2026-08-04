import { BrandMark } from '@/components/booking/brand-mark';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, ChartNoAxesColumn, Clock, LogOut, Scissors, UserRound, Users } from 'lucide-react';
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
    const page = usePage<{ auth: { user: { name: string; email: string } | null; is_owner: boolean }; flash: { success: string | null } }>();
    const { auth } = page.props;
    const items = NAV.filter((item) => !item.ownerOnly || auth.is_owner);
    const current = page.url.split('?')[0];

    return (
        <div className="bg-background text-foreground min-h-svh md:flex md:items-start">
            {/* em tela grande a sidebar fica parada e só o conteúdo rola */}
            <aside className="border-border/80 bg-card md:sticky md:top-0 md:flex md:h-svh md:w-60 md:shrink-0 md:flex-col md:overflow-y-auto md:border-r">
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
                <header className="border-border/80 flex flex-wrap items-center gap-3 border-b px-4 py-5 md:px-8">
                    <div className="min-w-0 flex-1">
                        <h1 className="text-2xl leading-tight">{title}</h1>
                        {subtitle && <p className="text-muted-foreground mt-1 text-sm">{subtitle}</p>}
                    </div>
                    {actions}
                </header>

                <div className="px-4 py-6 md:px-8">{children}</div>
            </main>

            <Toast message={page.props.flash?.success ?? null} />
        </div>
    );
}

/** Confirmação curta das ações do painel; some sozinha em 3s. */
function Toast({ message }: { message: string | null }) {
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
        <div className="border-success/40 bg-success/10 text-success fixed right-4 bottom-4 z-50 rounded-xl border px-4 py-2 text-sm shadow-lg">
            {message}
        </div>
    );
}
