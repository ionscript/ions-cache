<?php

namespace Ions\Cache\Adapter;

use Ions\Std\AbstractOptions;

/**
 * Class AdapterOptions
 * @package Ions\Cache\Adapter
 */
class AdapterOptions extends AbstractOptions
{
    /**
     * @var
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $namespace = '';

    /**
     * @var bool
     */
    protected $readable = true;

    /**
     * @var int
     */
    protected $ttl = 0;

    /**
     * @var bool
     */
    protected $writable = true;

    /**
     * @param null $adapter
     * @return $this
     */
    public function setAdapter($adapter = null)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * @param $namespace
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $namespace = (string)$namespace;

        if ($this->namespace !== $namespace) {
            $this->namespace = $namespace;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param $readable
     * @return $this
     */
    public function setReadable($readable)
    {
        $readable = (bool)$readable;

        if ($this->readable !== $readable) {
            $this->readable = $readable;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getReadable()
    {
        return $this->readable;
    }

    /**
     * @param $ttl
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setTtl($ttl)
    {
        $this->normalizeTtl($ttl);

        if ($this->ttl !== $ttl) {
            $this->ttl = $ttl;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * @param $writable
     * @return $this
     */
    public function setWritable($writable)
    {
        $writable = (bool)$writable;

        if ($this->writable !== $writable) {
            $this->writable = $writable;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getWritable()
    {
        return $this->writable;
    }

    /**
     * @param $ttl
     * @throws \InvalidArgumentException
     */
    protected function normalizeTtl(&$ttl)
    {
        if (!is_int($ttl)) {
            $ttl = (float)$ttl;

            if ($ttl === (float)(int)$ttl) {
                $ttl = (int)$ttl;
            }
        }

        if ($ttl < 0) {
            throw new \InvalidArgumentException("TTL can't be negative");
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = [];

        foreach ($this as $key => $value) {
            $array[$key] = $value;
        }

        return $array;
    }
}
