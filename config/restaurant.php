<?php

return [
    'locales' => ['en', 'bn'],

    'ordering_enabled' => env('ORDERING_ENABLED', true),
    'currency_symbol' => env('CURRENCY_SYMBOL', '৳'),

    'ordering' => [
        'delivery_enabled' => env('ORDER_DELIVERY', true),
        'pickup_enabled' => env('ORDER_PICKUP', true),
        'delivery_fee' => (float) env('DELIVERY_FEE', 60),
        'free_delivery_over' => (float) env('FREE_DELIVERY_OVER', 1500),
        'min_order' => (float) env('MIN_ORDER', 200),
        'max_qty' => 20,
        'max_lines' => 30,
        'online_payment' => env('ONLINE_PAYMENT', false),
        'unpaid_expiry_minutes' => (int) env('UNPAID_ORDER_EXPIRY', 60),
        'alert_email' => env('ORDER_ALERT_EMAIL'),
        'city' => env('ORDER_CITY', 'Dhaka'),
    ],

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

    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@meher.test'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
