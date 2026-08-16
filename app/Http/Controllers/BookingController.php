<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Service;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    /** Landing pública com os dois caminhos principais. */
    public function home(): Response
    {
        return Inertia::render('home');
    }

    /** Wizard público: o catálogo vem no primeiro render, a disponibilidade é buscada por XHR. */
    public function index(): Response
    {
        return Inertia::render('agendar/wizard', [
            'services' => Service::active()->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'duration_min' => $service->duration_min,
                    'price_cents' => $service->price_cents,
                ]),
            'barbers' => Barber::active()->get()
                ->map(fn (Barber $barber) => [
                    'id' => $barber->id,
                    'name' => $barber->display_name,
                    'headline' => $barber->headline,
                    'initials' => $barber->initials,
                ]),
            'shop' => [
                'name' => config('barbearia.name'),
                'address' => config('barbearia.address'),
                'timezone' => config('barbearia.timezone'),
                'cancel_window_hours' => (int) config('barbearia.cancel_window_hours'),
                'reservation_ttl_min' => (int) config('barbearia.reservation_ttl_min'),
            ],
        ]);
    }
}
