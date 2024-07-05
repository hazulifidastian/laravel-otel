<?php

namespace Hazuli\LaravelOtel\Support;

use Hazuli\LaravelOtel\Facades\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class OpenTelemetryMonologArrayRecordHandler extends AbstractProcessingHandler
{
    protected function write(array $record): void
    {
        $levelsTransformer = [
            100 => 'debug',
            200 => 'info',
            250 => 'notice',
            300 => 'warning',
            400 => 'error',
            500 => 'critical',
            550 => 'alert',
            600 => 'emergency',
        ];

        $level = $levelsTransformer[$record['level']];

        Logger::log($level, $record['message'], $record['context']);
    }
}