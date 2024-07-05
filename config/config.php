<?php

use \Illuminate\Support\Str;

/*
 * You can place your custom package configuration in here.
 */
return [
    'cache_store' => env('OTEL_CACHE_STORE', 'file'),

    'service_name' => env('OTEL_SERVICE_NAME', Str::slug(env('APP_NAME', 'laravel-app'))),

    'deployment_environment' => env('OTEL_DEPLOYMENT_ENVIRONMENT', 'development'),

    'traces' => [
        'exporter' => env('OTEL_TRACES_EXPORTER', 'otlp'),
        'endpoint' => env('OTEL_TRACES_ENDPOINT', 'http://localhost:4318/v1/traces'),
        'force_flush' => env('OTEL_TRACES_FORCE_FLUSH', true),
        'sampler' => [
            /**
             * Wraps the sampler in a parent based sampler
             */
            'parent' => env('OTEL_TRACES_SAMPLER_PARENT', true),

            /**
             * Sampler type
             * Supported values: "always_on", "always_off", "traceidratio"
             */
            'type' => env('OTEL_TRACES_SAMPLER_TYPE', 'always_on'),

            'args' => [
                'ratio' => env('OTEL_TRACES_SAMPLER_TRACEIDRATIO_RATIO', 0.05),
            ],
        ],
        'middleware' => [
            'kindservertrace_routes' => explode(',', str_replace(' ', '', env('OTEL_TRACES_MIDDLEWARE_KINDSERVERTRACE_ROUTES', ''))),
            'record_livewire_referers' => explode(',', str_replace(' ', '', env('OTEL_TRACES_MIDDLEWARE_RECORD_LIVEWIRE_REFERERS', ''))),
        ],
        'record_db_query' => env('OTEL_TRACES_RECORD_DB_QUERY', false),
        'record_notification_failed' => env('OTEL_TRACES_RECORD_NOTIFICATION_FAILED', false),
        'record_events' => explode(',', str_replace(' ', '', env('OTEL_TRACES_RECORD_EVENTS', ''))),
        'record_cache' => env('OTEL_TRACES_RECORD_CACHE', false),
        'record_queue' => env('OTEL_TRACES_RECORD_QUEUE', false),
    ],
    'metrics' => [
        // otel or prometheus
        'provider' => env('OTEL_METRICS_PROVIDER', 'otel'),
        'endpoint' => env('OTEL_METRICS_ENDPOINT', 'http://localhost:4318/v1/metrics'),
        'force_flush' => env('OTEL_METRICS_FORCE_FLUSH', true),
        'middleware' => [
            'requesttotalmetric_routes' => explode(',', str_replace(' ', '', env('OTEL_METRICS_MIDDLEWARE_REQTOTAL_ROUTES', ''))),
            'requestlatencymetric_routes' => explode(',', str_replace(' ', '', env('OTEL_METRICS_MIDDLEWARE_REQLATENCY_ROUTES', ''))),
        ]
    ],
    'logs' => [
        'exporter' => env('OTEL_LOGS_EXPORTER', 'otlp'),
        'endpoint' => env('OTEL_LOGS_ENDPOINT', ''),
        'force_flush' => env('OTEL_LOGS_FORCE_FLUSH', true),
    ]
];