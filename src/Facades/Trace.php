<?php

namespace Hazuli\LaravelOtel\Facades;

use Illuminate\Support\Facades\Facade;

class Trace extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'otel-trace';
    }
}
