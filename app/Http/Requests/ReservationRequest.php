<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cfg = config('restaurant.reservation');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:' . now()->addDays($cfg['advance_days'])->toDateString(),
            ],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'between:1,' . $cfg['max_party']],
            'message' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
