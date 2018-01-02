<?php

namespace Ions\Cache\Adapter;

/**
 * Class Memcache
 * @package Ions\Cache\Adapter
 */
class Memcache extends AbstractAdapter
{
    /**
     * @var bool
     */
    protected $initialized = false;
    /**
     * @var
     */
    protected $resourceManager;
    /**
     * @var
     */
    protected $resourceId;
    /**
     * @var string
     */
    protected $namespacePrefix = '';

    /**
     * Memcache constructor.
     * @param null $options
     * @throws \InvalidArgumentException
     */
    public function __construct($options = null)
    {
        if (version_compare('2.0.0', phpversion('memcache')) > 0) {
            throw new \InvalidArgumentException('Missing ext/memcache version >= 2.0.0');
        }

        parent::__construct($options);
    }

    /**
     * @return mixed
     */
    protected function getMemcacheResource()
    {
        if ($this->initialized) {
            return $this->resourceManager->getResource($this->resourceId);
        }

        $options = $this->getOptions();
        $this->resourceManager = $options->getResourceManager();
        $this->resourceId = $options->getResourceId();
        $this->namespacePrefix = '';
        $namespace = $options->getNamespace();

        if ($namespace !== '') {
            $this->namespacePrefix = $namespace . $options->getNamespaceDelimiter();
        }

        $this->initialized = true;

        return $this->resourceManager->getResource($this->resourceId);
    }

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (!$options instanceof MemcacheOptions) {
            $options = new MemcacheOptions($options);
        }

        return parent::setOptions($options);
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new MemcacheOptions());
        }
        return $this->options;
    }

    /**
     * @param $value
     * @return int
     */
    protected function getWriteFlag(& $value)
    {
        if (!$this->getOptions()->getCompression()) {
            return 0;
        }
        return (is_bool($value) || is_int($value) || is_float($value)) ? 0 : MEMCACHE_COMPRESSED;
    }

    /**
     * @return bool|\RuntimeException
     */
    public function flush()
    {
        $memc = $this->getMemcacheResource();
        if (!$memc->flush()) {
            return new \RuntimeException('Memcache flush failed');
        }
        return true;
    }

    /**
     * @return \RuntimeException
     */
    public function getTotalSpace()
    {
        $memc = $this->getMemcacheResource();
        $stats = $memc->getExtendedStats();
        if ($stats === false) {
            return new \RuntimeException('Memcache getStats failed');
        }
        $mem = array_pop($stats);
        return $mem['limit_maxbytes'];
    }

    /**
     * @return mixed
     * @throws \RuntimeException
     */
    public function getAvailableSpace()
    {
        $memc = $this->getMemcacheResource();
        $stats = $memc->getExtendedStats();
        if ($stats === false) {
            throw new \RuntimeException('Memcache getStats failed');
        }
        $mem = array_pop($stats);
        return $mem['limit_maxbytes'] - $mem['bytes'];
    }

    /**
     * @param $normalizedKey
     * @param null $success
     * @param null $casToken
     * @return mixed
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $memc = $this->getMemcacheResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $result = $memc->get($internalKey);
        $success = ($result !== false);
        if ($result === false) {
            return null;
        }
        $casToken = $result;
        return $result;
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $memc = $this->getMemcacheResource();
        $value = $memc->get($this->namespacePrefix . $normalizedKey);
        return ($value !== false);
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcacheResource();
        $expiration = $this->expirationTime();
        $flag = $this->getWriteFlag($value);
        if (!$memc->set($this->namespacePrefix . $normalizedKey, $value, $flag, $expiration)) {
            throw new \RuntimeException('Memcache set value failed');
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     */
    protected function internalAddItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcacheResource();
        $expiration = $this->expirationTime();
        $flag = $this->getWriteFlag($value);
        return $memc->add($this->namespacePrefix . $normalizedKey, $value, $flag, $expiration);
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcacheResource();
        $expiration = $this->expirationTime();
        $flag = $this->getWriteFlag($value);
        return $memc->replace($this->namespacePrefix . $normalizedKey, $value, $flag, $expiration);
    }

    /**
     * @param $normalizedKey
     * @return mixed
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $memc = $this->getMemcacheResource();
        return $memc->delete($this->namespacePrefix . $normalizedKey, 0);
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     * @throws \RuntimeException
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcacheResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $value = (int)$value;
        $newValue = $memc->increment($internalKey, $value);
        if ($newValue !== false) {
            return $newValue;
        }
        $newValue = $value;
        if (!$memc->add($internalKey, $newValue, 0, $this->expirationTime())) {
            throw new \RuntimeException('Memcache unable to add increment value');
        }
        return $newValue;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     * @throws \RuntimeException
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcacheResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $value = (int)$value;
        $newValue = $memc->decrement($internalKey, $value);
        if ($newValue !== false) {
            return $newValue;
        }
        $newValue = -$value;
        if (!$memc->add($internalKey, $newValue, 0, $this->expirationTime())) {
            throw new \RuntimeException('Memcache unable to add decrement value');
        }
        return $newValue;
    }

    /**
     * @return int
     */
    protected function expirationTime()
    {
        $ttl = $this->getOptions()->getTtl();
        if ($ttl > 2592000) {
            return time() + $ttl;
        }
        return $ttl;
    }
}
