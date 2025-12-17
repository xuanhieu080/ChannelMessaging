<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShopifyOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'                  => ['nullable', 'string', 'max:200'],
            'financial_status'   => ['nullable', 'string', 'max:50'],
            'fulfillment_status' => ['nullable', 'string', 'max:50'],
            'from'               => ['nullable', 'date'],
            'to'                 => ['nullable', 'date'],
            'per_page'           => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
