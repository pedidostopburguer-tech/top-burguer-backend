<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('customer_phone')) {
            $this->merge([
                'customer_phone' => preg_replace('/\D/', '', $this->customer_phone),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_phone' => ['required', 'string', 'min:10', 'max:15'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_text' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
