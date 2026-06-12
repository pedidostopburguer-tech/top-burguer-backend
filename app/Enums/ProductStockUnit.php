<?php

namespace App\Enums;

/**
 * Unidade de medida do estoque do produto.
 *
 * un      — unidade (itens contáveis, ex: hambúrgueres).
 * porcao  — porção (ex: batata frita compartilhada).
 * g       — gramas (ex: ingredientes a granel).
 * ml      — mililitros (ex: bebidas).
 */
enum ProductStockUnit: string
{
    case Unidade = 'un';
    case Porcao = 'porção';
    case Grama = 'g';
    case Mililitro = 'ml';
}
