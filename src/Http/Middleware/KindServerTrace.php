<?php

namespace Hazuli\LaravelOtel\Http\Middleware;

use Closure;
use Hazuli\LaravelOtel\Facades\Span;
use Hazuli\LaravelOtel\Support\FilterRoute;
use Hazuli\LaravelOtel\Support\SpanBuilder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KindServerTrace
{
    public function __construct()
    {

    }

    public function handle($request, Closure $next)
    {
        $routes = config('laravel-otel.traces.middleware.kindservertrace_routes');

        $filterRoute = new FilterRoute($routes, $request->route()->uri());

        if ($filterRoute->isEmpty()) {
            return $next($request);
        }

        if (!$filterRoute->isAll() && !$filterRoute->isMatch()) {
            return $next($request);
        }

        /**
         * Jika route '..livewire/update'
         * 1. Periksa apakah konfigurasi rekam livewire true
         * 2. Periksa apakah header referer ada dalam konfigurasi
         */
        if (Str::endsWith($request->route()->uri(), 'livewire/update')) {
            $livewireReferers = config('laravel-otel.traces.middleware.record_livewire_referers');
            $headerReferer = $request->headers->get('referer');

            $filterRoute = new FilterRoute($livewireReferers, $headerReferer);

            if ($filterRoute->isEmpty()) {
                return $next($request);
            }

            if (!$filterRoute->isAll() && !$filterRoute->isMatch()) {
                return $next($request);
            }

            if (empty($livewireReferers)) {
                return $next($request);
            }
        }

        Span::start((new SpanBuilder)->server($request));

        $response = $next($request);

        if ($response instanceof BinaryFileResponse) {
            $statusCode = $response->getStatusCode();
            $statusText = BinaryFileResponse::$statusTexts[$statusCode];
        } else {
            $statusCode = $response->status();
            $statusText = $response->statusText();
        }

        Span::response($response);

        if ($statusCode === 200) {
            Span::ok();
        } elseif ($statusCode >= 400 && $statusCode < 600) {  // 4xx - 5xx error
            Span::error($statusText);
        }

        if (property_exists($response, 'exception') and $response->exception) {
            Span::exception($response->exception);
        }

        Span::stop()->detach();

        return $response;
    }
}
