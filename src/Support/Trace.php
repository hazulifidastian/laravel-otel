<?php

namespace Hazuli\LaravelOtel\Support;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

class Trace
{
    public function __construct()
    {

    }

    public function getTracer(string $name = 'io.opentelemetry.contrib.php'): TracerInterface
    {
        return Globals::tracerProvider()->getTracer($name);
    }

    public function getTraceContextPropagator(): TraceContextPropagator
    {
        return TraceContextPropagator::getInstance();
    }

    public function getParentContext(array $from): ContextInterface
    {
        return $this->getTraceContextPropagator()->extract($from);
    }

    public function injectContext(array &$headers): void
    {
        $this->getTraceContextPropagator()->inject($headers);
    }

    public function getCurrentContext(): Context
    {
        return Context::getCurrent();
    }

    public function propagationHeaders(?ContextInterface $context = null): array
    {
        $headers = [];

        $this->injectContext($headers, $context);

        return $headers;
    }

}