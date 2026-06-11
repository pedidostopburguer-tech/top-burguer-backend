<?php

namespace App\Enums;

/**
 * Status manual da mesa (controle do staff).
 *
 * livre   — mesa disponível para novos clientes.
 * ocupada — mesa com clientes/comanda em andamento.
 * limpeza — mesa liberada pelo cliente, aguardando higienização antes de voltar a 'livre'.
 */
enum TableStatus: string
{
    case Livre = 'livre';
    case Ocupada = 'ocupada';
    case Limpeza = 'limpeza';
}
