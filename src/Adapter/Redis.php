<?php

namespace Ions\Cache\Adapter;

use Redis as RedisResource;
use RedisException as RedisResourceException;

/**
 * Class Redis
 * @package Ions\Cache\Adapter
 */
class Redis extends AbstractAdapter
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
     * Redis constructor.
     * @param null $options
     * @throws \RuntimeException
     */
    public function __construct($options = null)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not loaded');
        }
        parent::__construct($options);
        $initialized = &$this->initialized;
        $this->getEventManager()->attach('option', function () use (& $initialized) {
            $initialized = false;
        });
    }

    /**
     * @return mixed
     */
    protected function getRedisResource()
    {
        if (!$this->initialized) {
            $options = $this->getOptions();
            $this->resourceManager = $options->getResourceManager();
            $this->resourceId = $options->getResourceId();
            $namespace = $options->getNamespace();
            if ($namespace !== '') {
                $this->namespacePrefix = $namespace . $options->getNamespaceDelimiter();
            } else {
                $this->namespacePrefix = '';
            }
            $this->initialized = true;
        }
        return $this->resourceManager->getResource($this->resourceId);
    }

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (!$options instanceof RedisOptions) {
            $options = new RedisOptions($options);
        }
        return parent::setOptions($options);
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new RedisOptions());
        }
        return $this->options;
    }

    /**
     * @param $normalizedKey
     * @param null $success
     * @param null $casToken
     * @return mixed
     * @throws \RuntimeException
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $redis = $this->getRedisResource();
        try {
            $value = $redis->get($this->namespacePrefix . $normalizedKey);
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
        if ($value === false) {
            $success = false;
            return null;
        }
        $success = true;
        $casToken = $value;
        return $value;
    }

    /**
     * @param $normalizedKey
     * @return mixed
     * @throws \RuntimeException
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $redis = $this->getRedisResource();
        try {
            return $redis->exists($this->namespacePrefix . $normalizedKey);
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     * @throws \RuntimeException
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $redis = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl = $options->getTtl();
        try {
            if ($ttl) {
                if ($options->getResourceManager()->getMajorVersion($options->getResourceId()) < 2) {
                    throw new \RuntimeException("To use ttl you need version >= 2.0.0");
                }
                $success = $redis->setex($this->namespacePrefix . $normalizedKey, $ttl, $this->preSerialize($value));
            } else {
                $success = $redis->set($this->namespacePrefix . $normalizedKey, $this->preSerialize($value));
            }
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
        return $success;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     * @throws \RuntimeException
     */
    protected function internalAddItem(& $normalizedKey, & $value)
    {
        $redis = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl = $options->getTtl();
        try {
            if ($ttl) {
                if ($options->getResourceManager()->getMajorVersion($options->getResourceId()) < 2) {
                    throw new \RuntimeException("To use ttl you need version >= 2.0.0");
                }
                $success = $redis->setnx($this->namespacePrefix . $normalizedKey, $this->preSerialize($value));
                if ($success) {
                    $redis->expire($this->namespacePrefix . $normalizedKey, $ttl);
                }
            } else {
                $success = $redis->setnx($this->namespacePrefix . $normalizedKey, $this->preSerialize($value));
            }
            return $success;
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
    }

    /**
     * @param $normalizedKey
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalTouchItem(& $normalizedKey)
    {
        $redis = $this->getRedisResource();
        try {
            $ttl = $this->getOptions()->getTtl();
            return (bool)$redis->expire($this->namespacePrefix . $normalizedKey, $ttl);
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
    }

    /**
     * @param $normalizedKey
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $redis = $this->getRedisResource();
        try {
            return (bool)$redis->delete($this->namespacePrefix . $normalizedKey);
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     * @throws \RuntimeException
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $redis = $this->getRedisResource();
        try {
            return $redis->incrBy($this->namespacePrefix . $normalizedKey, $value);
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     * @throws \RuntimeException
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $redis = $this->getRedisResource();
        try {
            return $redis->decrBy($this->namespacePrefix . $normalizedKey, $value);
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
    }

    /**
     * @return mixed
     * @throws \RuntimeException
     */
    public function flush()
    {
        $redis = $this->getRedisResource();
        try {
            return $redis->flushDB();
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
    }

    /**
     * @param $namespace
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function clearByNamespace($namespace)
    {
        $redis = $this->getRedisResource();
        $namespace = (string)$namespace;
        if ($namespace === '') {
            throw new \InvalidArgumentException('No namespace given');
        }
        $options = $this->getOptions();
        $prefix = $namespace . $options->getNamespaceDelimiter();
        $redis->delete($redis->keys($prefix . '*'));
        return true;
    }

    /**
     * @param $prefix
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function clearByPrefix($prefix)
    {
        $redis = $this->getRedisResource();
        $prefix = (string)$prefix;
        if ($prefix === '') {
            throw new \InvalidArgumentException('No prefix given');
        }
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter() . $prefix;
        $redis->delete($redis->keys($prefix . '*'));
        return true;
    }

    /**
     * @return mixed
     * @throws \RuntimeException
     */
    public function getTotalSpace()
    {
        $redis = $this->getRedisResource();
        try {
            $info = $redis->info();
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
        return $info['used_memory'];
    }

    /**
     * @param $normalizedKey
     * @return array|bool
     * @throws \RuntimeException
     */
    protected function internalGetMetadata(& $normalizedKey)
    {
        $redis = $this->getRedisResource();
        $metadata = [];
        try {
            $redisVersion = $this->resourceManager->getVersion($this->resourceId);
            if (version_compare($redisVersion, '2.8', '>=')) {
                $pttl = $redis->pttl($this->namespacePrefix . $normalizedKey);
                if ($pttl <= -2) {
                    return false;
                }
                $metadata['ttl'] = ($pttl == -1) ? null : $pttl / 1000;
            } elseif (version_compare($redisVersion, '2.6', '>=')) {
                $pttl = $redis->pttl($this->namespacePrefix . $normalizedKey);
                if ($pttl <= -1) {
                    if (!$this->internalHasItem($normalizedKey)) {
                        return false;
                    }
                    $metadata['ttl'] = null;
                } else {
                    $metadata['ttl'] = $pttl / 1000;
                }
            } elseif (version_compare($redisVersion, '2', '>=')) {
                $ttl = $redis->ttl($this->namespacePrefix . $normalizedKey);
                if ($ttl <= -1) {
                    if (!$this->internalHasItem($normalizedKey)) {
                        return false;
                    }
                    $metadata['ttl'] = null;
                } else {
                    $metadata['ttl'] = $ttl;
                }
            } elseif (!$this->internalHasItem($normalizedKey)) {
                return false;
            }
        } catch (RedisResourceException $e) {
            throw new \RuntimeException($redis->getLastError(), $e->getCode(), $e);
        }
        return $metadata;
    }

    /**
     * @param $value
     * @return string
     */
    protected function preSerialize($value)
    {
        $options = $this->getOptions();
        $resourceMgr = $options->getResourceManager();
        $serializer = $resourceMgr->getLibOption($options->getResourceId(), RedisResource::OPT_SERIALIZER);
        if ($serializer === null) {
            return (string)$value;
        }
        return $value;
    }
}
