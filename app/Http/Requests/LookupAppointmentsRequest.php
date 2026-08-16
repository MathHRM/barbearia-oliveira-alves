<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LookupAppointmentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30'],
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

    public function messages(): array
    {
        return [
            'phone.required' => 'Informe o WhatsApp usado no agendamento.',
        ];
    }
}
