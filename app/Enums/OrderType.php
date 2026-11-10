<?php

namespace App\Enums;

enum OrderType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
    case DineIn = 'dine_in';

    /** For customers: translated. */
    public function label(): string
    {
        return __('orders.type.' . $this->value);
    }

    /** For staff screens and documents. */
    public function adminLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
