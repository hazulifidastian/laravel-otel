<?php

namespace Hazuli\LaravelOtel\Facades;

use Illuminate\Support\Facades\Facade;

class Metric extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'otel-metric';
    }
}
