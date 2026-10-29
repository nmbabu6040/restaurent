<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Assigned, self::PickedUp], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Delivered => 'bg-green-800 text-white',
            self::Failed => 'bg-red-700 text-white',
            self::Cancelled => 'bg-charcoal text-white',
            self::PickedUp => 'bg-navy text-white',
            self::Assigned => 'bg-terracotta-dark text-white',
        };
    }
}
