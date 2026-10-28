<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('orders.status.' . $this->value);
    }

    public function adminLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** @return array<int, self> */
    public function transitions(OrderType $type): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [$type === OrderType::Delivery ? self::OutForDelivery : self::Ready, self::Cancelled],
            self::Ready, self::OutForDelivery => [self::Completed],
            default => [],
        };
    }

    /** @return array<int, self> */
    public static function steps(OrderType $type): array
    {
        return [
            self::Pending,
            self::Confirmed,
            self::Preparing,
            $type === OrderType::Delivery ? self::OutForDelivery : self::Ready,
            self::Completed,
        ];
    }

    public function badge(): string
    {
        return match ($this) {
            self::Completed => 'bg-green-800 text-white',
            self::Cancelled => 'bg-charcoal text-white',
            self::Pending => 'bg-terracotta-dark text-white',
            default => 'bg-navy text-white',
        };
    }
}
