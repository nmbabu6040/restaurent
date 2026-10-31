<?php

namespace App\Support;

use App\Models\ContactMessage;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\Review;
use App\Services\AnalyticsService;
use Carbon\Carbon;

/** What can be downloaded from Admin > Downloads. Each entry: label, whether it can be limited by dates, column headers, and a row generator. */
final class Exports
{
    private static function between($query, string $column, ?Carbon $from, ?Carbon $to)
    {
        return $query->when($from, fn($q) => $q->where($column, '>=', $from))->when($to, fn($q) => $q->where($column, '<=', $to));
    }

    public static function all(): array
    {
        $day = fn($d) => $d?->format('Y-m-d H:i');

        return [
            'orders' => [
                'label' => 'Orders',
                'dated' => true,
                'headers' => ['Reference', 'Date', 'Type', 'Status', 'Payment', 'Paid', 'Customer', 'Phone', 'Area', 'Subtotal', 'Delivery fee', 'Discount', 'Coupon', 'Total'],
                'rows' => fn($from, $to) => self::between(Order::query(), 'created_at', $from, $to)->lazyById(500)->map(fn($o) => [
                    $o->reference,
                    $day($o->created_at),
                    $o->type->value,
                    $o->status->value,
                    $o->payment_method,
                    $o->payment_status,
                    $o->name,
                    $o->phone,
                    $o->zone_en,
                    $o->subtotal,
                    $o->delivery_fee,
                    $o->discount,
                    $o->coupon_code,
                    $o->total,
                ]),
            ],
            'sales_lines' => [
                'label' => 'Sold items (every order line)',
                'dated' => true,
                'headers' => ['Date', 'Order', 'Dish', 'Options', 'Quantity', 'Unit price', 'Line total', 'Unit cost'],
                'rows' => fn($from, $to) => OrderItem::query()->with('order:id,reference,created_at')
                    ->whereHas('order', fn($q) => self::between($q, 'created_at', $from, $to))->lazyById(500)
                    ->map(fn($l) => [$day($l->order?->created_at), $l->order?->reference, $l->name_en, $l->optionsLabel('en'), $l->quantity, $l->unit_price, $l->line_total, $l->unit_cost]),
            ],
            'reservations' => [
                'label' => 'Reservations',
                'dated' => true,
                'headers' => ['Reference', 'When', 'Guests', 'Name', 'Phone', 'Email', 'Status', 'Message'],
                'rows' => fn($from, $to) => self::between(Reservation::query(), 'reserved_at', $from, $to)->lazyById(500)->map(fn($r) => [
                    $r->reference,
                    $day($r->reserved_at),
                    $r->party_size,
                    $r->name,
                    $r->phone,
                    $r->email,
                    $r->status->value,
                    $r->message,
                ]),
            ],
            'messages' => [
                'label' => 'Contact messages',
                'dated' => true,
                'headers' => ['Date', 'Name', 'Email', 'Phone', 'Subject', 'Message', 'Read'],
                'rows' => fn($from, $to) => self::between(ContactMessage::query(), 'created_at', $from, $to)->lazyById(500)->map(fn($m) => [
                    $day($m->created_at),
                    $m->name,
                    $m->email,
                    $m->phone,
                    $m->subject,
                    $m->message,
                    $m->read_at ? 'yes' : 'no',
                ]),
            ],
            'subscribers' => [
                'label' => 'Newsletter subscribers',
                'dated' => false,
                'headers' => ['Email', 'Language', 'Subscribed', 'Status'],
                'rows' => fn() => NewsletterSubscriber::query()->lazyById(500)->map(fn($s) => [
                    $s->email,
                    $s->locale,
                    $day($s->subscribed_at),
                    $s->unsubscribed_at ? 'unsubscribed' : 'active',
                ]),
            ],
            'customers' => [
                'label' => 'Customers (from orders)',
                'dated' => false,
                'headers' => ['Name', 'Phone', 'Orders', 'Total spent', 'First order', 'Last order'],
                'rows' => fn() => collect(app(AnalyticsService::class)->allCustomers())->sortByDesc('spent')->map(fn($c) => [
                    $c['name'],
                    $c['phone'],
                    $c['orders'],
                    number_format($c['spent'], 2, '.', ''),
                    $c['first']->format('Y-m-d'),
                    $c['last']->format('Y-m-d'),
                ]),
            ],
            'expenses' => [
                'label' => 'Expenses',
                'dated' => true,
                'headers' => ['Date', 'Category', 'Amount', 'Description', 'Paid to'],
                'rows' => fn($from, $to) => self::between(Expense::query()->with('supplier:id,name'), 'expense_date', $from?->copy()->startOfDay(), $to)->lazyById(500)->map(fn($e) => [
                    $e->expense_date->format('Y-m-d'),
                    ExpenseCategories::all()[$e->category] ?? $e->category,
                    $e->amount,
                    $e->description,
                    $e->supplier?->name,
                ]),
            ],
            'ingredients' => [
                'label' => 'Ingredients and stock',
                'dated' => false,
                'headers' => ['Ingredient', 'Unit', 'In stock', 'Reorder level', 'Cost per unit', 'Stock value', 'Supplier', 'Active'],
                'rows' => fn() => Ingredient::query()->with('supplier:id,name')->lazyById(500)->map(fn($i) => [
                    $i->name,
                    $i->unit,
                    qty($i->stock),
                    qty($i->reorder_level),
                    qty($i->unit_cost),
                    number_format(max(0, (float) $i->stock) * (float) $i->unit_cost, 2, '.', ''),
                    $i->supplier?->name,
                    $i->is_active ? 'yes' : 'no',
                ]),
            ],
            'menu_items' => [
                'label' => 'Menu items',
                'dated' => false,
                'headers' => ['Dish', 'Category', 'Price', 'Available', 'Featured', 'Stock mode', 'Stock', 'Cost', 'Reviews', 'Rating'],
                'rows' => fn() => MenuItem::query()->with('category:id,name_en')->lazyById(500)->map(fn($m) => [
                    $m->name_en,
                    $m->category?->name_en,
                    $m->price,
                    $m->is_available ? 'yes' : 'no',
                    $m->is_featured ? 'yes' : 'no',
                    $m->stock_mode,
                    $m->stock_mode === 'quantity' ? $m->stock_qty : '',
                    $m->cost_price,
                    $m->reviews_count,
                    $m->rating_avg,
                ]),
            ],
            'reviews' => [
                'label' => 'Reviews',
                'dated' => true,
                'headers' => ['Date', 'Dish', 'Reviewer', 'Rating', 'Verified buyer', 'Status', 'Comment'],
                'rows' => fn($from, $to) => self::between(Review::query()->with('item:id,name_en'), 'created_at', $from, $to)->lazyById(500)->map(fn($r) => [
                    $day($r->created_at),
                    $r->item?->name_en,
                    $r->name,
                    $r->rating,
                    $r->is_verified ? 'yes' : 'no',
                    $r->status,
                    $r->comment,
                ]),
            ],
            'deliveries' => [
                'label' => 'Rider deliveries',
                'dated' => true,
                'headers' => ['Assigned', 'Order', 'Rider', 'Status', 'Rider fee', 'Cash collected', 'Delivered', 'Settled'],
                'rows' => fn($from, $to) => self::between(Delivery::query()->with('order:id,reference', 'rider'), 'assigned_at', $from, $to)->lazyById(500)->map(fn($d) => [
                    $day($d->assigned_at),
                    $d->order?->reference,
                    $d->rider?->name,
                    $d->status->value,
                    $d->rider_fee,
                    $d->cash_collected,
                    $day($d->delivered_at),
                    $day($d->settled_at),
                ]),
            ],
        ];
    }
}
