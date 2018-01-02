<?php

namespace Ions\Cache;

/**
 * Interface CacheInterface
 * @package Ions\Cache
 */
interface CacheInterface
{
    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public function set($key, $value);

    /**
     * @param $key
     * @return mixed
     */
    public function get($key);
}
