<?php

namespace Hazuli\LaravelOtel\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class Storage
{
    private Repository $cache;

    public function __construct()
    {
        $this->cache = Cache::store(config('laravel-otel.cache_store'));
    }

    /**
     * Set storage
     *
     * @param string $key
     * @param $value
     * @param int $expire default 30 days
     * @return void
     */
    public function set(string $key, $value, int $expire = 60 * 60 * 24 * 30)
    {
        $this->cache->put($key, $value, $expire);

        return $value;
    }

    public function get(string $key)
    {
        return $this->cache->get($key);
    }

    public function add(string $key, $addition)
    {
        if ($this->cache->has($key)) {
            return $this->set($key, $this->get($key) + $addition);
        }

        return $this->set($key, $addition);
    }

    public function keyFromArray(array $array): string
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[] = "$key: $value";
        }
        return implode(', ', $result);
    }
}
