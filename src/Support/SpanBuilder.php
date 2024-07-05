<?php

namespace Hazuli\LaravelOtel\Support;

use Hazuli\LaravelOtel\Facades\Trace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\RequestInterface;
use Spatie\Backtrace\Frame;

class SpanBuilder
{
    public string $name;
    public ContextInterface $parentContext;
    public int $spanKind;
    public array $attributes;

    public function __construct()
    {
        $this->attributes = [];
    }

    public function server(Request $request): self
    {
        $referer = '';
        if (Str::endsWith($request->route()->uri(), 'livewire/update')) {
            $referer = ' <-- ' . $request->headers->get('referer');
        }

        $this->name = $request->getMethod() . ' ' . $request->getPathInfo() . $referer;
        $this->spanKind = SpanKind::KIND_SERVER;
        $this->parentContext = Trace::getParentContext($request->headers->all());
        $this->attributes = [
            TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            TraceAttributes::URL_PATH => $request->getPathInfo(),
            TraceAttributes::URL_SCHEME => $request->getScheme(),
            TraceAttributes::HTTP_ROUTE => $request->route()->uri(),
            TraceAttributes::NETWORK_PROTOCOL_NAME => null,
            TraceAttributes::SERVER_PORT => $request->getPort(),
            TraceAttributes::URL_QUERY => $request->getQueryString(),
            TraceAttributes::URL_FULL => $request->fullUrl(),
            TraceAttributes::CLIENT_ADDRESS => $request->getClientIp(),
            TraceAttributes::SERVER_ADDRESS => $request->getHost(),

            TraceAttributes::NETWORK_PROTOCOL_VERSION => $request->getProtocolVersion(),
            TraceAttributes::ENDUSER_ID => $request->user()->email ?? null,
        ];

        $this->headers($request->headers->all());

        return $this;
    }

    public function livewireServer($request, Frame $frame): self
    {
        $this->name = $request->getMethod() . ' ' . $request->route()->uri() . ' ' . $frame->class . '::' . $frame->method;
        $this->spanKind = SpanKind::KIND_SERVER;
        $this->parentContext = Trace::getParentContext($request->headers->all());
        $this->attributes = [
            TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            TraceAttributes::URL_PATH => $request->getPathInfo(),
            TraceAttributes::URL_SCHEME => $request->getScheme(),
            TraceAttributes::HTTP_ROUTE => $request->route()->uri(),
            TraceAttributes::NETWORK_PROTOCOL_NAME => null,
            TraceAttributes::SERVER_PORT => $request->getPort(),
            TraceAttributes::URL_QUERY => $request->getQueryString(),
            TraceAttributes::CLIENT_ADDRESS => $request->getClientIp(),
            TraceAttributes::SERVER_ADDRESS => $request->getHost(),

            TraceAttributes::NETWORK_PROTOCOL_VERSION => $request->getProtocolVersion(),
            TraceAttributes::ENDUSER_ID => request()->user()->email ?? null,
        ];

        $this->headers($request->headers->all());
        $this->codeinfo($frame);

        return $this;
    }

    public function client(RequestInterface $request): self
    {
        $this->name = $request->getMethod() . ' ' . $request->getUri()->getHost() . $request->getUri()->getPath();
        $this->spanKind = SpanKind::KIND_CLIENT;
        $this->parentContext = Trace::getParentContext($request->getHeaders());
        $this->attributes = [
            TraceAttributes::HTTP_REQUEST_METHOD => $request->getMethod(),
            TraceAttributes::SERVER_ADDRESS => $request->getUri()->getHost(),
            TraceAttributes::SERVER_PORT => $request->getUri()->getPort(),
            TraceAttributes::URL_PATH => $request->getUri()->getPath(),
            TraceAttributes::URL_QUERY => $request->getUri()->getQuery(),
            TraceAttributes::NETWORK_PROTOCOL_VERSION => $request->getProtocolVersion(),
            TraceAttributes::URL_SCHEME => $request->getUri()->getScheme(),
        ];

        $this->headers($request->getHeaders());

        return $this;
    }

    public function internal(string $name): self
    {
        $this->name = $name;
        $this->spanKind = SpanKind::KIND_INTERNAL;
        $this->parentContext = Trace::getCurrentContext();

        return $this;
    }

    public function database(string $name=''): self
    {
        $prefix = 'DB';
        $this->name = $prefix . ' ' . $name;
        $this->spanKind = SpanKind::KIND_INTERNAL;
        $this->parentContext = Trace::getCurrentContext();

        return $this;
    }

    public function failedNotification(string $name=''): self
    {
        $prefix = 'FAILED_NOTIFICATION';
        $this->name = $prefix . ' ' . $name;
        $this->spanKind = SpanKind::KIND_INTERNAL;
        $this->parentContext = Trace::getCurrentContext();

        return $this;
    }

    public function headers(array $headers)
    {
        $requestHeaderPrefix = 'http.request.header.';

        foreach ($headers as $key=>$value) {
            if (is_array($value)) {
                $value = $value[0];
            }
            $this->attributes[$requestHeaderPrefix . $key] = $value;
        }
    }

    public function codeinfo(Frame $frame): self
    {
        $function = $frame->class
            ? $frame->class . '::' . $frame->method
            : $frame->method;

        $this->attributes = array_merge($this->attributes, [
            TraceAttributes::CODE_FILEPATH => $frame->file,
            TraceAttributes::CODE_LINENO => $frame->lineNumber,
            TraceAttributes::CODE_FUNCTION => $function,
        ]);

        return $this;
    }
}
