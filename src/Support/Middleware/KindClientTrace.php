<?php

namespace Hazuli\LaravelOtel\Support\Middleware;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Hazuli\LaravelOtel\Facades\Span;
use Hazuli\LaravelOtel\Facades\Trace;
use Hazuli\LaravelOtel\Support\SpanBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class KindClientTrace
{
    public function __construct()
    {
    }

    public static function make(): Closure
    {
        return static function (callable $handler): callable {
            return static function (RequestInterface $request, array $options) use ($handler) {
                Span::start((new SpanBuilder())->client($request));

                $context = Trace::getCurrentContext();
                foreach (Trace::propagationHeaders($context) as $key => $value) {
                    $request = $request->withHeader($key, $value);
                }

                Span::updateAttributes(['http.request.header.traceparent' => $request->getHeader('traceparent')]);

                $promise = $handler($request, $options);
                assert($promise instanceof PromiseInterface);

                return $promise->then(function (ResponseInterface $response) {
                    Span::response($response);

                    if ($response->getStatusCode() === 200) {
                        Span::ok();
                    } else {
                        Span::error($response->getReasonPhrase());
                    }

                    // TODO record exception

                    Span::stop()->detach();

                    return $response;
                });
            };
        };
    }
}
