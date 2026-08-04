<?php

namespace App\Exceptions;

use RuntimeException;

/** Horário sumiu entre listar e reservar — o cliente precisa escolher outro. */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Esse horário acabou de ser ocupado. Escolha outro.')
    {
        parent::__construct($message);
    }
}
