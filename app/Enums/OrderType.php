<?php

namespace App\Enums;

enum OrderType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    public function label(): string
    {
        return __('orders.type.' . $this->value);
    }
}
