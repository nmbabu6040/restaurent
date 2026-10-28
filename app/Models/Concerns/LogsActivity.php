<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                if (! Auth::check()) {
                    return;
                }

                $name = class_basename($model);

                ActivityLog::record(
                    strtolower($name) . '.' . $event,
                    "{$name} #{$model->getKey()} {$event}",
                    $model,
                );
            });
        }
    }
}
