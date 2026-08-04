<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Services\Asaas\ProcessWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    /**
     * O Asaas reenvia o evento até receber 200, então a idempotência vem do
     * unique em webhook_events.external_id: duplicata é aceita e ignorada.
     */
    public function __invoke(Request $request, ProcessWebhookEvent $processor): JsonResponse
    {
        $expected = (string) config('barbearia.asaas.webhook_token');

        if ($expected !== '' && ! hash_equals($expected, (string) $request->header('asaas-access-token'))) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $payload = $request->all();
        $externalId = (string) ($payload['id'] ?? '');
        $event = (string) ($payload['event'] ?? '');

        if ($externalId === '' || $event === '') {
            return response()->json(['message' => 'Payload incompleto.'], 422);
        }

        $record = WebhookEvent::where('external_id', $externalId)->first();

        // reenvio do que já deu certo é descartado; o que falhou tenta de novo
        if ($record !== null && $record->processed_at !== null) {
            return response()->json(['message' => 'Evento já processado.']);
        }

        try {
            $record ??= WebhookEvent::create([
                'provider' => 'asaas',
                'external_id' => $externalId,
                'event' => $event,
                'payload' => $payload,
            ]);
        } catch (QueryException $exception) {
            // corrida entre dois reenvios simultâneos: o unique resolve, o segundo desiste
            if (str_contains($exception->getMessage(), 'webhook_events_external_id_unique')) {
                return response()->json(['message' => 'Evento já processado.']);
            }

            throw $exception;
        }

        try {
            $processor->handle($event, $payload);
            $record->update(['processed_at' => now()]);
        } catch (\Throwable $exception) {
            // guarda o erro e devolve 500: o Asaas repete e a próxima tentativa acha o evento pendente
            $record->update(['error' => $exception->getMessage()]);
            Log::error('Falha ao processar webhook do Asaas', ['event' => $externalId, 'erro' => $exception->getMessage()]);

            return response()->json(['message' => 'Falha ao processar.'], 500);
        }

        return response()->json(['message' => 'ok']);
    }
}
