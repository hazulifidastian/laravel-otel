<?php

namespace Hazuli\LaravelOtel\Listeners;

use Hazuli\LaravelOtel\Facades\Span;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;

class CacheListener
{
    public function __invoke($event) {
        if ($event instanceof CacheHit) {
            Span::addEvent('cache hit', [
                'key' => $event->key,
                'tags' => json_encode($event->tags),
            ]);
        }

        if ($event instanceof CacheMissed) {
            Span::addEvent('cache hit', [
                'key' => $event->key,
                'tags' => json_encode($event->tags),
            ]);
        }

        if ($event instanceof KeyWritten) {
            $ttl = $event->seconds ?? 0;

            Span::addEvent('cache set', [
                'key' => $event->key,
                'tags' => json_encode($event->tags),
                'expires_at' => $ttl > 0 ? now()->addSeconds($ttl)->getTimestamp() : 'never',
                'expires_in_seconds' => $ttl > 0 ? $ttl : 'never',
                'expires_in_human' => $ttl > 0 ? now()->addSeconds($ttl)->diffForHumans() : 'never',
            ]);
        }

        if ($event instanceof KeyForgotten) {
            Span::addEvent('cache forget', [
                'key' => $event->key,
                'tags' => json_encode($event->tags),
            ]);
        }
    }
}