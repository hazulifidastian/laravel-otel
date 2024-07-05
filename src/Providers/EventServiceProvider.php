<?php

namespace Hazuli\LaravelOtel\Providers;

use Hazuli\LaravelOtel\Listeners\CacheListener;
use Hazuli\LaravelOtel\Listeners\NotificationFailedListener;
use Hazuli\LaravelOtel\Listeners\QueryExecutedListener;
use Hazuli\LaravelOtel\Listeners\SpecificEventListener;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as IlluminateEventServiceProvider;
use Illuminate\Notifications\Events\NotificationFailed;

class EventServiceProvider extends IlluminateEventServiceProvider
{
    public function listens()
    {
        $listens = [];

        if (config('laravel-otel.traces.record_db_query')) {
            $listens[QueryExecuted::class] = [QueryExecutedListener::class];
        }

        if (config('laravel-otel.traces.record_notification_failed')) {
            $listens[NotificationFailed::class] = [NotificationFailedListener::class];
        }

        foreach(config('laravel-otel.traces.record_events') as $event) {
            $listens[$event] = [SpecificEventListener::class];
        }

        if (config('laravel-otel.traces.record_cache')) {
            $listens[CacheHit::class] = [CacheListener::class];
            $listens[CacheMissed::class] = [CacheListener::class];
            $listens[KeyWritten::class] = [CacheListener::class];
            $listens[KeyForgotten::class] = [CacheListener::class];
        }

        return $listens;
    }

    public function boot()
    {
        parent::boot();
    }
}