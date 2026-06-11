<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada ao tentar desativar (DELETE) uma mesa que possui pedido em aberto
 * (channel='mesa', table_number correspondente, status fora de Finalizado/Recusado).
 *
 * Mapeada para HTTP 409 (Conflict) no TableController.
 */
class TableHasOpenOrderException extends Exception
{
    protected $message = 'Esta mesa possui pedido(s) em aberto e não pode ser desativada.';
}
