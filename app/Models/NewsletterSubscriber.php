<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'token', 'locale', 'subscribed_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}
