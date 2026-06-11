<?php

namespace App\Enums;

/**
 * Tipo de desconto do cupom.
 *
 * percentage    — desconto percentual sobre o subtotal.
 * fixed         — desconto de valor fixo (limitado ao subtotal).
 * free_delivery — zera a taxa de entrega.
 */
enum CouponDiscountType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeDelivery = 'free_delivery';
}
