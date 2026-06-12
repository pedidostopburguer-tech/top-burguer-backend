<?php

namespace App\Enums;

/**
 * Canal de origem do pedido.
 *
 * delivery — pedido para entrega no endereço do cliente.
 * mesa     — pedido feito via Modo Mesa (QR Code / `table_number` preenchido).
 * balcao   — pedido lançado pelo staff no balcão (PDV, Card 14).
 */
enum OrderChannel: string
{
    case Delivery = 'delivery';
    case Mesa = 'mesa';
    case Balcao = 'balcao';
}
