<?php

namespace App\Http\Requests;

use App\Actions\CreateCharge;
use App\Support\Document;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAppointmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'starts_at' => ['required', 'date'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'document' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
            'billing_type' => ['required', 'in:'.CreateCharge::PIX.','.CreateCharge::CARD],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('phone') && ! Phone::isValid((string) $this->input('phone'))) {
                    $validator->errors()->add('phone', 'Informe um WhatsApp válido com DDD.');
                }

                if ($this->filled('document') && ! Document::isValidCpf((string) $this->input('document'))) {
                    $validator->errors()->add('document', 'CPF inválido.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Precisamos do seu nome.',
            'phone.required' => 'Precisamos do seu WhatsApp para confirmar.',
            'document.required' => 'O CPF é exigido pelo pagamento.',
        ];
    }
}
