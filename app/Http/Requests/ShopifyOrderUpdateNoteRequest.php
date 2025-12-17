<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShopifyOrderUpdateNoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'note' => ['required','string','max:5000'],
            'mode' => ['nullable','in:append,replace'],
        ];
    }
}
