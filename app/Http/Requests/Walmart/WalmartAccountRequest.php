<?php

namespace App\Http\Requests\Walmart;

use Illuminate\Foundation\Http\FormRequest;

class WalmartAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'client_id' => ['nullable','string','max:255'],
            'client_secret' => ['nullable','string','max:255'],
            'consumer_id' => ['nullable','string','max:255'],
            'private_key_pem' => ['nullable','string'],
            'market' => ['required','string','max:10'],
            'is_active' => ['nullable','boolean'],
            'auto_sync_enabled' => ['nullable','boolean'],
            'auto_sync_minutes' => ['nullable','integer','min:1','max:1440'],
        ];
    }
}
