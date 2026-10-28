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

    /** @return array<int, self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Rejected, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            default => [],
        };
    }

    public function label(): string
    {
        return __('forms.reservation.status.' . $this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Confirmed => 'bg-green-800 text-white',
            self::Rejected, self::Cancelled => 'bg-charcoal text-white',
            self::Pending => 'bg-terracotta-dark text-white',
        };
    }
}
