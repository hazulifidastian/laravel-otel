<?php

namespace Hazuli\LaravelOtel\Support;

use Hazuli\LaravelOtel\Facades\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class OpenTelemetryMonologHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $level = $record->level->toPsrLogLevel();

        Logger::log($level, $record->message, $record->context);
    }
}