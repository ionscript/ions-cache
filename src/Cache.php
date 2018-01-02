<?php

namespace Ions\Cache;

/**
 * Class Cache
 * @package Ions\Cache
 */
class Cache implements CacheInterface
{
    /**
     * @var
     */
    protected static $adapter;

    /**
     * @var array
     */
    protected static $adapters = [
        'apc' => Adapter\Apc::class,
        'apcu' => Adapter\Apcu::class,
        'null' => Adapter\Null::class,
        'file' => Adapter\Filesystem::class,
        'memcache' => Adapter\Memcache::class,
        'memcached' => Adapter\Memcached::class,
        'memory' => Adapter\Memory::class,
        'mongo' => Adapter\MongoDb::class,
        'redis' => Adapter\Redis::class
    ];

    /**
     * Cache constructor.
     * @param array $options
     * @throws \InvalidArgumentException
     */
    public function __construct(array $options)
    {
        if (!isset($options['type'])) {
            throw new \InvalidArgumentException('Missing options "type"');
        }

        if (array_key_exists($options['type'], static::$adapters)) {
            static::$adapter = new static::$adapters[$options['type']];

            if (isset($options['options'])) {
                static::$adapter->setOptions($options['options']);
            }
        }
    }

    /**
     * @param $key
     * @return mixed
     */
    public function has($key)
    {
        return static::$adapter->hasItem($key);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        return static::$adapter->getItem($key);
    }

    /**
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        static::$adapter->setItem($key, $value);
    }

    /**
     * @return void
     */
    public function clearExpired()
    {
        static::$adapter->clearExpired();
    }
}
