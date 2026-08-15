<?php

return [
    'name' => env('BARBEARIA_NAME', 'Barbearia Oliveira Alves'),

    // endereço exibido na confirmação e no .ics
    'address' => env('BARBEARIA_ADDRESS', 'Av. Márcia Antônia, 1052 · Tupanuara · São Joaquim de Bicas/MG'),

    // Fuso da barbearia. O app roda em UTC (grava timestamptz sem ambiguidade);
    // grade de horários, agenda e exibição usam este fuso.
    'timezone' => env('BARBEARIA_TZ', 'America/Sao_Paulo'),

    // prazo, em horas antes do atendimento, para o cliente cancelar
    'cancel_window_hours' => (int) env('BARBEARIA_CANCEL_WINDOW_HOURS', 12),

    // antecedência mínima entre agora e o início do horário ofertado
    'min_lead_min' => (int) env('BARBEARIA_MIN_LEAD_MIN', 60),

    // até quantos dias à frente a agenda pública abre
    'horizon_days' => (int) env('BARBEARIA_HORIZON_DAYS', 21),

    // granularidade dos inícios de horário
    'slot_step_min' => (int) env('BARBEARIA_SLOT_STEP_MIN', 15),

    // dias sem voltar para o cliente contar como perdido
    'churn_days' => (int) env('BARBEARIA_CHURN_DAYS', 60),

];
