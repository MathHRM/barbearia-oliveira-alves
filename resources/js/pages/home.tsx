import { BrandMark } from '@/components/booking/brand-mark';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Search } from 'lucide-react';

export default function Home() {
    return (
        <div className="bg-background text-foreground min-h-svh">
            <Head title="Início" />

            <main className="mx-auto flex min-h-svh w-full max-w-xl flex-col justify-between px-5 py-8 sm:px-8 sm:py-12">
                <header>
                    <BrandMark className="items-start" />
                </header>

                <section className="space-y-8 py-16">
                    <div className="space-y-4">
                        <p className="eyebrow">Seu próximo corte começa aqui</p>
                        <h1 className="max-w-md text-4xl leading-tight sm:text-5xl">Cuidado no detalhe. Presença no resultado.</h1>
                        <p className="text-muted-foreground max-w-sm text-base leading-relaxed">
                            Escolha seu horário ou consulte uma visita já marcada usando o WhatsApp informado na reserva.
                        </p>
                    </div>

                    <div className="grid gap-3">
                        <Button asChild size="lg" className="h-14 justify-between px-5 text-base">
                            <Link href="/agendar">
                                <span className="flex items-center gap-3">
                                    <CalendarDays className="size-5" />
                                    Agendar
                                </span>
                                <span aria-hidden="true">→</span>
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline" className="h-14 justify-between px-5 text-base">
                            <Link href="/agendamentos">
                                <span className="flex items-center gap-3">
                                    <Search className="size-5" />
                                    Ver meus agendamentos
                                </span>
                                <span aria-hidden="true">→</span>
                            </Link>
                        </Button>
                    </div>
                </section>

                <p className="text-muted-foreground text-xs">Atendimento com hora marcada · São Joaquim de Bicas/MG</p>
            </main>
        </div>
    );
}
