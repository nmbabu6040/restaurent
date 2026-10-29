<?php

namespace App\Http\Requests;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-reports') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Typing dates without choosing a period means "custom dates".
        if (! $this->filled('range') && $this->filled('from') && $this->filled('to')) {
            $this->merge(['range' => 'custom']);
        }
    }

    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(array_keys(AnalyticsService::PRESETS))],
            'from' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
            'to' => [
                'bail',
                'nullable',
                'required_if:range,custom',
                'date_format:Y-m-d',
                'after_or_equal:from',
                function (string $attribute, mixed $value, \Closure $fail) {
                    try {
                        $from = Carbon::createFromFormat('Y-m-d', (string) $this->input('from'));
                        $to = Carbon::createFromFormat('Y-m-d', (string) $value);
                    } catch (\Throwable) {
                        return;
                    }

                    if ($from->diffInDays($to) > 365) {
                        $fail('Please choose a period of at most one year.');
                    }
                },
            ],
            'group' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
        ];
    }

    /** @return array{preset:string, from:Carbon, to:Carbon, group:string, days:int, prev_from:Carbon, prev_to:Carbon} */
    public function period(): array
    {
        $preset = (string) $this->input('range', '30d');
        [$from, $to] = app(AnalyticsService::class)->resolveRange($preset, $this->input('from'), $this->input('to'));

        $days = (int) round($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay())) + 1;

        return [
            'preset' => $preset,
            'from' => $from,
            'to' => $to,
            'group' => $this->input('group') ?: ($days <= 45 ? 'daily' : ($days <= 180 ? 'weekly' : 'monthly')),
            'days' => $days,
            // The period of the same length just before this one, for the "vs previous" comparison.
            'prev_from' => $from->copy()->subDays($days),
            'prev_to' => $from->copy()->subDay()->endOfDay(),
        ];
    }
}
