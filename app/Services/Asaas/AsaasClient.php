<?php

namespace App\Services\Asaas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP fino da API v3 do Asaas. Só o transporte mora aqui: quem decide
 * o que virar cobrança é a action CreateCharge.
 */
class AsaasClient
{
    /** @return array<string, mixed> */
    public function createCustomer(array $payload): array
    {
        return $this->request()->post('/customers', $payload)->throw()->json();
    }

    /** @return array<string, mixed> */
    public function createPayment(array $payload): array
    {
        return $this->request()->post('/payments', $payload)->throw()->json();
    }

    /** @return array<string, mixed> {encodedImage, payload, expirationDate} */
    public function pixQrCode(string $paymentId): array
    {
        return $this->request()->get("/payments/{$paymentId}/pixQrCode")->throw()->json();
    }

    /** @return array<string, mixed> */
    public function refund(string $paymentId, ?int $amountCents = null): array
    {
        $payload = $amountCents === null ? [] : ['value' => $amountCents / 100];

        return $this->request()->post("/payments/{$paymentId}/refund", $payload)->throw()->json();
    }

    /** @return array<string, mixed> */
    public function payment(string $paymentId): array
    {
        return $this->request()->get("/payments/{$paymentId}")->throw()->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('barbearia.asaas.base_url'))
            ->withHeaders([
                'access_token' => (string) config('barbearia.asaas.key'),
                'User-Agent' => config('barbearia.name'),
            ])
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }
}
