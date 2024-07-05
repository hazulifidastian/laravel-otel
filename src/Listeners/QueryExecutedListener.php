<?php

namespace Hazuli\LaravelOtel\Listeners;

use Hazuli\LaravelOtel\Facades\Span;
use Hazuli\LaravelOtel\Support\SpanBuilder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\TraceAttributeValues;

class QueryExecutedListener
{
    public function handle(QueryExecuted $event){
        $end = now();
        $start = $end->copy()->sub((float) $event->time, 'milliseconds');

        // Trace only span that has parent
        if (Span::isRoot()) {
            return;
        }

        Span::start((new SpanBuilder())->database(Str::of($event->sql)->limit(50, ' (...)')), (int) $start->getPreciseTimestamp() * 1_000);
        Span::setAttributes([
            TraceAttributes::DB_SYSTEM => TraceAttributeValues::DB_SYSTEM_MYSQL,
            'db.query.text' => $event->sql,
            'db.query.bindings' => json_encode($event->bindings),
            'db.query.operation' => $this->extractDbOperation($event->sql),
            'db.query.operation.duration' => $event->time,
        ]);
        Span::stop((int) $end->getPreciseTimestamp() * 1_000)->detach();
    }

    private function extractDbOperation(string $sql): ?string
    {
        if (Str::startsWith(Str::upper($sql), 'SELECT')) {
            return 'SELECT';
        }

        if (Str::startsWith(Str::upper($sql), 'INSERT')) {
            return 'INSERT';
        }

        if (Str::startsWith(Str::upper($sql), 'UPDATE')) {
            return 'UPDATE';
        }

        if (Str::startsWith(Str::upper($sql), 'DELETE')) {
            return 'DELETE';
        }

        return null;
    }
}