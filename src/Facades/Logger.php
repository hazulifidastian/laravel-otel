<?php

namespace Hazuli\LaravelOtel\Facades;

use Illuminate\Support\Facades\Facade;

class Logger extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Hazuli\LaravelOtel\Support\Logger::class;
    }
}
