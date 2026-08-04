<?php

namespace App\Http\Controllers;

use App\Actions\ReserveAppointment;
use App\Exceptions\SlotUnavailableException;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    /** Passo 04 → 05: prende o horário e devolve o token que a tela de pagamento usa. */
    public function store(StoreAppointmentRequest $request, ReserveAppointment $reserve): JsonResponse
    {
        $data = $request->validated();
        $service = Service::active()->findOrFail($data['service_id']);

        try {
            $appointment = $reserve->handle(
                $service,
                $data['barber_id'] ?? null,
                Carbon::parse($data['starts_at']),
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'note' => $data['note'] ?? null,
                ],
            );
        } catch (SlotUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'token' => $appointment->public_token,
            'code' => $appointment->code(),
            'reserved_until' => $appointment->reserved_until?->toIso8601String(),
        ], 201);
    }
}
