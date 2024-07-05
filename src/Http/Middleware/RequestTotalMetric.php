<?php

namespace Hazuli\LaravelOtel\Http\Middleware;

use Closure;
use Hazuli\LaravelOtel\Facades\Metric;
use Hazuli\LaravelOtel\Facades\Storage;
use Hazuli\LaravelOtel\Support\FilterRoute;

class RequestTotalMetric
{
    public function __construct()
    {
    }

    public function handle($request, Closure $next)
    {
        $routes = config('laravel-otel.metrics.middleware.requesttotalmetric_routes');
        $filterRoute = new FilterRoute($routes, $request->route()->uri());

        if ($filterRoute->isEmpty()) {
            return $next($request);
        }

        if (!$filterRoute->isAll() && !$filterRoute->isMatch()) {
            return $next($request);
        }

        $response = $next($request);

        $this->collectMetrics($request, $response);

        return $response;
    }

    private function collectMetrics($request, $response)
    {
        $meterName = 'http.server.request.total';

        $serverRequestTotal = Metric::getDefaultMeter($meterName);
        $labels = [
            'url.path' => $request->route()->uri(),
            'http.method' => $request->getMethod(),
            'http.status_code' => $response->getStatusCode(),
        ];
        $serverRequestTotalValue = Storage::add($meterName . Storage::keyFromArray($labels), 1);
        $serverRequestTotal->add($serverRequestTotalValue, $labels);
        Metric::collect();
    }
}
