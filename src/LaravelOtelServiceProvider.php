<?php

namespace Hazuli\LaravelOtel;

use Composer\InstalledVersions;
use OpenTelemetry\SDK\Sdk;
use Hazuli\LaravelOtel\Support\Storage;
use Hazuli\LaravelOtel\Providers\EventServiceProvider;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Metrics\View\CriteriaViewRegistry;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\StalenessHandler\ImmediateStalenessHandlerFactory;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarFilter\WithSampledTraceExemplarFilter;
use OpenTelemetry\SemConv\TraceAttributes;

class LaravelOtelServiceProvider extends ServiceProvider
{
    private ExportingReader $metricsReader;

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('laravel-otel.php'),
            ], 'config');
        }

        $this->app->singleton('laravel-otel', function () {
            return new LaravelOtel();
        });

        $resource = $this->buildResource(); 
        $tracerProvider = $this->buildTracerProvider($resource);
        $propagator = TraceContextPropagator::getInstance();
        $meterProvider = $this->buildMeterProvider();
        $loggerProvider = $this->buildLoggerProvider($resource);

        Sdk::builder()
            ->setPropagator($propagator)
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();

        $instrumentation = new CachedInstrumentation(
            'laravel-otel',
            class_exists(InstalledVersions::class) ? InstalledVersions::getPrettyVersion('hazuli/laravel-otel') : null,
            TraceAttributes::SCHEMA_URL,
        );
        $this->app->bind(TracerInterface::class, fn () => $instrumentation->tracer());
        $this->app->bind(MeterInterface::class, fn () => $instrumentation->meter());
        $this->app->bind(LoggerInterface::class, fn () => $instrumentation->logger());

        $this->app->terminating(function () use ($tracerProvider, $meterProvider, $loggerProvider) {
            if (config('laravel-otel.traces.force_flush')) {
                $tracerProvider->forceFlush();
            }
            if (config('laravel-otel.metrics.force_flush')) {
                $meterProvider->forceFlush();
            }
            if (config('laravel-otel.logs.force_flush')) {
                $loggerProvider->forceFlush();
            }
        });

        $this->app->singleton('otel-span', function () {
            return new \Hazuli\LaravelOtel\Support\Span();
        });

        $this->app->singleton('otel-trace', function () {
            return new \Hazuli\LaravelOtel\Support\Trace();
        });

        $this->app->singleton('otel-storage', function () {
            return new Storage();
        });

        $metricsReader = $this->metricsReader;
        $this->app->singleton('otel-metric', function () use ($metricsReader) {
            return new \Hazuli\LaravelOtel\Support\Metric(
                $metricsReader, Globals::meterProvider()->getMeter('io.opentelemetry.contrib.php'));
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'laravel-otel');

        $this->app->register(EventServiceProvider::class);

        // Register the main class to use with the facade
        $this->app->singleton('laravel-otel', function () {
            return new LaravelOtel;
        });
    }

    private function buildResource()
    {
        return ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => config('laravel-otel.service_name'),
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT => config('laravel-otel.deployment_environment'),
        ])));
    }

    private function buildTracerProvider(ResourceInfo $resource): TracerProviderInterface
    {
        if (config('laravel-otel.traces.exporter') === 'otlp') {
            $transport = (new OtlpHttpTransportFactory())->create(config('laravel-otel.traces.endpoint'), 'application/json');
        }

        $spanExporter = new SpanExporter($transport);
        $spanProcessor = (new BatchSpanProcessorBuilder($spanExporter))->build();

        $tracerProvider = TracerProvider::builder()
            ->setResource($resource)
            ->addSpanProcessor($spanProcessor)
            ->setSampler($this->buildSampler())
            ->build();

        return $tracerProvider;
    }

    private function buildMeterProvider(): MeterProviderInterface
    {
        $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => config('laravel-otel.service_name'),
        ])));

        $this->metricsReader = new ExportingReader(
            new MetricExporter(
                (new OtlpHttpTransportFactory())->create(config('laravel-otel.metrics.endpoint'), 'application/json')
            )
        );

        $meterProvider = new MeterProvider(
            null,
            $resource,
            ClockFactory::getDefault(),
            Attributes::factory(),
            new InstrumentationScopeFactory(Attributes::factory()),
            [$this->metricsReader],
            new CriteriaViewRegistry(),
            new WithSampledTraceExemplarFilter(),
            new ImmediateStalenessHandlerFactory(),
        );

        return $meterProvider;
    }

    private function buildLoggerProvider(ResourceInfo $resource): LoggerProviderInterface
    {
        $logExporter = new LogsExporter(
            (new OtlpHttpTransportFactory())->create(config('laravel-otel.logs.endpoint'), 'application/json')
        );

        $this->app->bind(LogRecordExporterInterface::class, fn () => $logExporter);

        $logProcessor = new BatchLogRecordProcessor($logExporter, ClockFactory::getDefault());

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor($logProcessor)
            ->build();

        return $loggerProvider;
    }

    private function buildSampler()
    {
        switch (config('laravel-otel.traces.sampler.type')) {
            case 'always_off':
                $sampler = new AlwaysOffSampler();
                break;
            case 'always_on':
                $sampler = new AlwaysOnSampler();
                break;
            case 'traceidratio':
                $sampler = new TraceIdRatioBasedSampler(config('laravel-otel.traces.sampler.args.ratio') ?? 0.05);
                break;
        }

        if (config('laravel-otel.traces.sampler.parent')) {
            $sampler = new ParentBased($sampler);
        }

        return $sampler;
    }
}
