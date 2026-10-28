<?php

namespace App\Http\Requests;

use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_map(fn($t) => $t->value, app(CheckoutService::class)->availableTypes());

        $methods = ['cod'];
        if (app(PaymentService::class)->enabled()) {
            $methods[] = 'sslcommerz';
        }

        return [
            'type' => ['required', Rule::in($types)],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address' => [Rule::requiredIf($this->input('type') === 'delivery'), 'nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in($methods)],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
