<?php

return [
    'locales' => ['en', 'bn'],

    'ordering_enabled' => env('ORDERING_ENABLED', true),
    'currency_symbol' => env('CURRENCY_SYMBOL', '৳'),

    'reservation' => [
        'capacity' => (int) env('RESERVATION_CAPACITY', 40),
        'duration_minutes' => (int) env('RESERVATION_DURATION', 90),
        'open' => env('RESERVATION_OPEN', '11:00'),
        'last_seating' => env('RESERVATION_LAST_SEATING', '21:30'),
        'step_minutes' => 30,
        'max_party' => 12,
        'advance_days' => 60,
        'min_notice_minutes' => 120,
    ],
];
