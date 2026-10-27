<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function blocksCapacity(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    public function label(): string
    {
        return __('forms.reservation.status.' . $this->value);
    }
}
