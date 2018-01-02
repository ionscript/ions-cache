<?php

namespace Ions\Cache\Adapter;

use Memcached as MemcachedResource;

/**
 * Class Memcached
 * @package Ions\Cache\Adapter
 */
class Memcached extends AbstractAdapter
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
     * Memcached constructor.
     * @param null $options
     * @throws \InvalidArgumentException
     */
    public function __construct($options = null)
    {
        if (phpversion('memcached') < 1) {
            throw new \InvalidArgumentException('Need ext/memcached version >= 1.0.0');
        }

        parent::__construct($options);
    }

    /**
     * @return mixed
     */
    protected function getMemcachedResource()
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
        if (!$options instanceof MemcachedOptions) {
            $options = new MemcachedOptions($options);
        }
        return parent::setOptions($options);
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new MemcachedOptions());
        }
        return $this->options;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $memc = $this->getMemcachedResource();
        if (!$memc->flush()) {
            throw $this->getExceptionByResultCode($memc->getResultCode());
        }
        return true;
    }

    /**
     * @return mixed
     */
    public function getTotalSpace()
    {
        $memc = $this->getMemcachedResource();
        $stats = $memc->getStats();
        if ($stats === false) {
            throw new \RuntimeException($memc->getResultMessage());
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
        $memc = $this->getMemcachedResource();
        $stats = $memc->getStats();
        if ($stats === false) {
            throw new \RuntimeException($memc->getResultMessage());
        }
        $mem = array_pop($stats);
        return $mem['limit_maxbytes'] - $mem['bytes'];
    }

    /**
     * @param $normalizedKey
     * @param null $success
     * @param null $casToken
     * @return null
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $memc = $this->getMemcachedResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        if (func_num_args() > 2) {
            $result = $memc->get($internalKey, null, $casToken);
        } else {
            $result = $memc->get($internalKey);
        }
        $success = true;
        if ($result === false) {
            $rsCode = $memc->getResultCode();
            if ($rsCode == MemcachedResource::RES_NOTFOUND) {
                $result = null;
                $success = false;
            } elseif ($rsCode) {
                $success = false;
                throw $this->getExceptionByResultCode($rsCode);
            }
        }
        return $result;
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $memc = $this->getMemcachedResource();
        $value = $memc->get($this->namespacePrefix . $normalizedKey);
        if ($value === false) {
            $rsCode = $memc->getResultCode();
            if ($rsCode == MemcachedResource::RES_SUCCESS) {
                return true;
            } elseif ($rsCode == MemcachedResource::RES_NOTFOUND) {
                return false;
            } else {
                throw $this->getExceptionByResultCode($rsCode);
            }
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcachedResource();
        $expiration = $this->expirationTime();
        if (!$memc->set($this->namespacePrefix . $normalizedKey, $value, $expiration)) {
            throw $this->getExceptionByResultCode($memc->getResultCode());
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     */
    protected function internalAddItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcachedResource();
        $expiration = $this->expirationTime();
        if (!$memc->add($this->namespacePrefix . $normalizedKey, $value, $expiration)) {
            if ($memc->getResultCode() == MemcachedResource::RES_NOTSTORED) {
                return false;
            }
            throw $this->getExceptionByResultCode($memc->getResultCode());
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcachedResource();
        $expiration = $this->expirationTime();
        if (!$memc->replace($this->namespacePrefix . $normalizedKey, $value, $expiration)) {
            $rsCode = $memc->getResultCode();
            if ($rsCode == MemcachedResource::RES_NOTSTORED) {
                return false;
            }
            throw $this->getExceptionByResultCode($rsCode);
        }
        return true;
    }

    /**
     * @param $token
     * @param $normalizedKey
     * @param $value
     * @return mixed
     */
    protected function internalCheckAndSetItem(& $token, & $normalizedKey, & $value)
    {
        $memc = $this->getMemcachedResource();
        $expiration = $this->expirationTime();
        $result = $memc->cas($token, $this->namespacePrefix . $normalizedKey, $value, $expiration);
        if ($result === false) {
            $rsCode = $memc->getResultCode();
            if ($rsCode !== 0 && $rsCode != MemcachedResource::RES_DATA_EXISTS) {
                throw $this->getExceptionByResultCode($rsCode);
            }
        }
        return $result;
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $memc = $this->getMemcachedResource();
        $result = $memc->delete($this->namespacePrefix . $normalizedKey);
        if ($result === false) {
            $rsCode = $memc->getResultCode();
            if ($rsCode == MemcachedResource::RES_NOTFOUND) {
                return false;
            } elseif ($rsCode != MemcachedResource::RES_SUCCESS) {
                throw $this->getExceptionByResultCode($rsCode);
            }
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcachedResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $value = (int)$value;
        $newValue = $memc->increment($internalKey, $value);
        if ($newValue === false) {
            $rsCode = $memc->getResultCode();
            if ($rsCode == MemcachedResource::RES_NOTFOUND) {
                $newValue = $value;
                $memc->add($internalKey, $newValue, $this->expirationTime());
                $rsCode = $memc->getResultCode();
            }
            if ($rsCode) {
                throw $this->getExceptionByResultCode($rsCode);
            }
        }
        return $newValue;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $memc = $this->getMemcachedResource();
        $internalKey = $this->namespacePrefix . $normalizedKey;
        $value = (int)$value;
        $newValue = $memc->decrement($internalKey, $value);
        if ($newValue === false) {
            $rsCode = $memc->getResultCode();
            if ($rsCode == MemcachedResource::RES_NOTFOUND) {
                $newValue = -$value;
                $memc->add($internalKey, $newValue, $this->expirationTime());
                $rsCode = $memc->getResultCode();
            }
            if ($rsCode) {
                throw $this->getExceptionByResultCode($rsCode);
            }
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

    /**
     * @param $code
     * @return \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function getExceptionByResultCode($code)
    {
        switch ($code) {
            case MemcachedResource::RES_SUCCESS:
                throw new \InvalidArgumentException("The result code '{$code}' (SUCCESS) isn't an error");
            default:
                return new \RuntimeException($this->getMemcachedResource()->getResultMessage());
        }
    }
}
