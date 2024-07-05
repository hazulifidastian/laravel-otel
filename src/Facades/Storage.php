<?php

namespace Hazuli\LaravelOtel\Facades;

use Illuminate\Support\Facades\Facade;

class Storage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'otel-storage';
    }
}
