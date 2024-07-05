<?php

namespace Hazuli\LaravelOtel\Http\Middleware;

use Closure;
use Hazuli\LaravelOtel\Facades\Metric;
use Hazuli\LaravelOtel\Facades\Storage;
use Hazuli\LaravelOtel\Support\FilterRoute;

class RequestLatencyMetric
{
    public function __construct()
    {
    }

    public function handle($request, Closure $next)
    {
        $start = \Carbon\Carbon::now();

        $routes = config('laravel-otel.metrics.middleware.requestlatencymetric_routes');
        $filterRoute = new FilterRoute($routes, $request->route()->uri());

        if ($filterRoute->isEmpty()) {
            return $next($request);
        }

        if (!$filterRoute->isAll() && !$filterRoute->isMatch()) {
            return $next($request);
        }

        $response = $next($request);

        $this->collectMetrics($request, $response, $start);

        return $response;
    }

    private function collectMetrics($request, $response, $start)
    {
        $now = \Carbon\Carbon::now();
        $durationMs = $now->diffInMilliseconds($start);

        $meterName = 'http.server.request.latency.bucket';
        $serverRequestLatency = Metric::getDefaultMeter($meterName);
        $labels = [
            'url.path' => $request->route()->uri(),
            'http.method' => $request->getMethod(),
            'http.status_code' => $response->getStatusCode(),
        ];
        $lessEquals = [
            100,
            300,
            500,
            700,
            1000,  // 1 detik
            2000,
            3000,
            5000,
            7000,
            10000,
            30000,
            60000,  // 60 detik
            '+Inf',
        ];
        foreach($lessEquals as $le) {
            if ($le === '+Inf') {
                $labels = array_merge($labels, ['le' => '+Inf']);
                break;
            }

            if ($durationMs <= $le) {
                $labels = array_merge($labels, ['le' => $le]);
                break;
            }
        }

        $serverRequestLatencyTotal = Storage::add($meterName . Storage::keyFromArray($labels), 1);
        $serverRequestLatency->add($serverRequestLatencyTotal, $labels);
        Metric::collect();
    }
}
