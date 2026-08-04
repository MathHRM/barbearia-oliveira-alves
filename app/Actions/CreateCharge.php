<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Asaas\AsaasClient;
use App\Support\Document;

/**
 * Cria a cobrança da reserva no Asaas. Pix devolve QR + copia-e-cola para pagar
 * dentro do wizard; cartão manda o cliente para a fatura hospedada (sem PCI aqui).
 */
class CreateCharge
{
    public const PIX = 'PIX';

    public const CARD = 'CREDIT_CARD';

    public function __construct(private readonly AsaasClient $asaas) {}

    public function handle(Appointment $appointment, string $billingType): Payment
    {
        $appointment->loadMissing('customer', 'service');

        $charge = $this->asaas->createPayment([
            'customer' => $this->asaasCustomerId($appointment->customer),
            'billingType' => $billingType,
            'value' => $appointment->price_cents / 100,
            'dueDate' => now()->timezone(config('barbearia.timezone'))->toDateString(),
            'description' => $appointment->service->name.' · '.$appointment->code(),
            'externalReference' => $appointment->public_token,
        ]);

        $payment = $appointment->payment()->create([
            'provider' => 'asaas',
            'provider_payment_id' => $charge['id'],
            'billing_type' => $billingType,
            'amount_cents' => $appointment->price_cents,
            'status' => PaymentStatus::Pending,
            'invoice_url' => $charge['invoiceUrl'] ?? null,
            'raw' => $charge,
        ]);

        if ($billingType === self::PIX) {
            $qr = $this->asaas->pixQrCode($charge['id']);

            $payment->update([
                'pix_payload' => $qr['payload'] ?? null,
                'pix_qr_base64' => $qr['encodedImage'] ?? null,
            ]);
        }

        return $payment->refresh();
    }

    /** O cliente do Asaas é criado uma vez e guardado no nosso cadastro. */
    private function asaasCustomerId(Customer $customer): string
    {
        if ($customer->asaas_customer_id) {
            return $customer->asaas_customer_id;
        }

        $created = $this->asaas->createCustomer([
            'name' => $customer->name,
            'cpfCnpj' => Document::digits((string) $customer->document),
            'email' => $customer->email,
            'mobilePhone' => ltrim($customer->phone_e164, '+'),
            'externalReference' => (string) $customer->id,
            'notificationDisabled' => true,
        ]);

        $customer->update(['asaas_customer_id' => $created['id']]);

        return $created['id'];
    }
}
