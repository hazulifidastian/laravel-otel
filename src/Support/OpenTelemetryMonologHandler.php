<?php

namespace Hazuli\LaravelOtel\Support;

use Hazuli\LaravelOtel\Facades\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class OpenTelemetryMonologHandler extends AbstractProcessingHandler
{
    protected function write(array|LogRecord $record): void
    {
        if ($record instanceof LogRecord) {
            $level = $record->level->toPsrLogLevel();
            $message = $record->message;
            $context = $record->context;
        } else {
            $level = $record['level']->toPsrLogLevel();
            $message = $record['message'];
            $context = $record['context'] ?? [];
        }

        Logger::log($level, $message, $context);
    }
}
