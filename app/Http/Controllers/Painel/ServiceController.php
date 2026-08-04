<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo. Mexer em preço ou duração não toca no que já foi agendado:
 * o valor fica congelado em `appointments.price_cents`.
 */
class ServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('painel/servicos', [
            'services' => Service::withCount('appointments')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_min' => $service->duration_min,
                    'price_cents' => $service->price_cents,
                    'sort_order' => $service->sort_order,
                    'active' => $service->active,
                    'appointments_count' => $service->appointments_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Service::create($this->validated($request) + ['sort_order' => (int) Service::max('sort_order') + 1]);

        return back()->with('success', 'Serviço criado.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request));

        return back()->with('success', 'Serviço atualizado.');
    }

    /** Serviço com histórico não some do banco: vira inativo e sai do catálogo público. */
    public function destroy(Service $service): RedirectResponse
    {
        if ($service->appointments()->exists()) {
            $service->update(['active' => false]);

            return back()->with('success', 'Serviço desativado (tem histórico).');
        }

        $service->delete();

        return back()->with('success', 'Serviço removido.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:480'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'active' => ['boolean'],
        ]);
    }
}
