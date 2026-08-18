import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { BrandMark } from '@/components/booking/brand-mark';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

/** Entrada do painel. Sem cadastro público: quem cria usuário é o dono. */
export default function Login({ status, canResetPassword }: LoginProps) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<{ email: string; password: string; remember: boolean }>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <div className="bg-background text-foreground flex min-h-svh flex-col items-center justify-center px-4">
            <Head title="Entrar no painel" />

            <div className="w-full max-w-sm space-y-8">
                <div className="flex flex-col items-center gap-3">
                    <BrandMark />
                    <p className="eyebrow">Painel da equipe</p>
                </div>

                {status && <p className="text-success text-center text-sm font-medium">{status}</p>}

                <form className="space-y-5" onSubmit={submit}>
                    <div className="space-y-1.5">
                        <Label htmlFor="email">E-mail</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            autoComplete="email"
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value)}
                            placeholder="voce@barbearia.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="space-y-1.5">
                        <div className="flex items-center">
                            <Label htmlFor="password">Senha</Label>
                            {canResetPassword && (
                                <TextLink href="/forgot-password" className="ml-auto text-xs">
                                    Esqueci a senha
                                </TextLink>
                            )}
                        </div>
                        <div className="relative">
                            <Input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                required
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                placeholder="••••••••"
                                className="pr-11"
                            />
                            <button
                                type="button"
                                aria-label={showPassword ? 'Ocultar senha' : 'Visualizar senha'}
                                onClick={() => setShowPassword((visible) => !visible)}
                                className="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex w-11 items-center justify-center transition-colors"
                            >
                                {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox id="remember" name="remember" checked={data.remember} onClick={() => setData('remember', !data.remember)} />
                        <Label htmlFor="remember" className="text-muted-foreground text-sm">
                            Continuar conectado
                        </Label>
                    </div>

                    <Button type="submit" className="w-full" size="lg" disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        Entrar
                    </Button>
                </form>
            </div>
        </div>
    );
}
