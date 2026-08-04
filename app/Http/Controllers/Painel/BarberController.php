<?php

namespace App\Http\Controllers\Painel;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Equipe: cada barbeiro carrega um usuário de acesso ao painel. */
class BarberController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('painel/barbeiros', [
            'barbers' => Barber::with('user')
                ->withCount('appointments')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Barber $barber) => [
                    'id' => $barber->id,
                    'name' => $barber->display_name,
                    'headline' => $barber->headline,
                    'initials' => $barber->initials,
                    'active' => $barber->active,
                    'appointments_count' => $barber->appointments_count,
                    'user' => [
                        'name' => $barber->user?->name,
                        'email' => $barber->user?->email,
                        'role' => $barber->user?->role->label(),
                    ],
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::Barber,
                'active' => true,
            ]);

            Barber::create([
                'user_id' => $user->id,
                'display_name' => $validated['name'],
                'headline' => $validated['headline'] ?? null,
                'initials' => $this->initials($validated['name']),
                'sort_order' => (int) Barber::max('sort_order') + 1,
                'active' => true,
            ]);
        });

        return back()->with('success', 'Barbeiro cadastrado.');
    }

    public function update(Request $request, Barber $barber): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:120'],
            'active' => ['boolean'],
            'email' => ['nullable', 'email', 'max:180', Rule::unique('users', 'email')->ignore($barber->user_id)],
            // senha só muda com a confirmação batendo
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($barber, $validated) {
            $barber->update([
                'display_name' => $validated['name'],
                'headline' => $validated['headline'] ?? null,
                'initials' => $this->initials($validated['name']),
                'active' => $validated['active'] ?? $barber->active,
            ]);

            $user = $barber->user;

            if ($user !== null) {
                $user->update(array_filter([
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
                    // barbeiro inativo perde o acesso ao painel junto
                    'active' => $validated['active'] ?? $barber->active,
                ], fn ($value) => $value !== null));
            }
        });

        return back()->with('success', 'Barbeiro atualizado.');
    }

    private function initials(string $name): string
    {
        return Str::upper(Str::substr($name, 0, 1).Str::substr(Str::afterLast(trim($name), ' '), 0, 1));
    }
}
