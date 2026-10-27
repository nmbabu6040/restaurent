<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
