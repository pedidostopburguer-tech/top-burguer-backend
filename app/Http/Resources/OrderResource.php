<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'address' => $this->address,
            'payment_method' => $this->payment_method,
            'items' => $this->items,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'rating' => $this->rating,
            'feedback_text' => $this->feedback_text,
            'production_started_at' => $this->production_started_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'channel' => $this->channel,
            'table_number' => $this->table_number,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
