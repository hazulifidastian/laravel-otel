<?php

namespace Hazuli\LaravelOtel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hazuli\LaravelOtel\Skeleton\SkeletonClass
 */
class LaravelOtel extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-otel';
    }
}
