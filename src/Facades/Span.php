<?php

namespace Hazuli\LaravelOtel\Facades;

use Illuminate\Support\Facades\Facade;

class Span extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'otel-span';
    }
}
