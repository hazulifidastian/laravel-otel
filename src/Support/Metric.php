<?php

namespace Hazuli\LaravelOtel\Support;

use Exception;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;

class Metric
{
    private $reader;

    public function __construct(ExportingReader $reader)
    {
        $this->reader = $reader;
    }

    public function getReader(): ExportingReader
    {
        return $this->reader;
    }

    public function getMeter(string $name = 'io.opentelemetry.contrib.php'): MeterInterface
    {
        return Globals::meterProvider()->getMeter($name);
    }

    public function collect(): bool
    {
        return $this->reader->collect();
    }

    public function getDefaultMeter(string $name)
    {
        switch($name) {
            case 'http.server.request.total':
            return $this->getMeter()
                ->createCounter('http.server.request.total', 'request', 'jumlah request');
            case 'http.server.request.latency':
            return $this->getMeter()
                ->createObservableUpDownCounter('http.server.request.latency', 'ms', 'latency request');
            case 'http.server.request.latency.bucket':
            return $this->getMeter()
                ->createCounter('http.server.request.latency.bucket', 'ms', 'latency request bucket');
            default:
                throw new Exception("Meter {$name} tidak tersedia.");
        }
    }
}