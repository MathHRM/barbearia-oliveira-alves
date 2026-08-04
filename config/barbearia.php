<?php

return [
    // minutos que o slot fica preso enquanto o pagamento não volta aprovado
    'reservation_ttl_min' => (int) env('BARBEARIA_RESERVATION_TTL_MIN', 10),

    // prazo, em horas antes do atendimento, para o cliente cancelar com estorno integral
    'cancel_window_hours' => (int) env('BARBEARIA_CANCEL_WINDOW_HOURS', 12),

    // antecedência mínima entre agora e o início do horário ofertado
    'min_lead_min' => (int) env('BARBEARIA_MIN_LEAD_MIN', 60),

    // até quantos dias à frente a agenda pública abre
    'horizon_days' => (int) env('BARBEARIA_HORIZON_DAYS', 21),

    // granularidade dos inícios de horário
    'slot_step_min' => (int) env('BARBEARIA_SLOT_STEP_MIN', 15),

    // dias sem voltar para o cliente contar como perdido
    'churn_days' => (int) env('BARBEARIA_CHURN_DAYS', 60),

    'asaas' => [
        'env' => env('ASAAS_ENV', 'sandbox'),
        'key' => env('ASAAS_API_KEY'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'base_url' => env('ASAAS_ENV', 'sandbox') === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3',
    ],
];
