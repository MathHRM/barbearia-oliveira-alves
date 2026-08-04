<?php

namespace App\Http\Requests\Painel;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreManualAppointmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'barber_id' => ['nullable', 'integer', 'exists:barbers,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('phone') && ! Phone::isValid((string) $this->input('phone'))) {
                    $validator->errors()->add('phone', 'Informe um WhatsApp válido com DDD.');
                }
            },
        ];
    }
}
