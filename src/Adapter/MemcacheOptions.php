<?php

namespace Ions\Cache\Adapter;

/**
 * Class MemcacheOptions
 * @package Ions\Cache\Adapter
 */
class MemcacheOptions extends AdapterOptions
{
    /**
     * @var string
     */
    protected $namespaceDelimiter = ':';
    /**
     * @var
     */
    protected $resourceManager;
    /**
     * @var string
     */
    protected $resourceId = 'default';
    /**
     * @var bool
     */
    protected $compression = false;

    /**
     * @param $namespace
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setNamespace($namespace)
    {
        $namespace = (string)$namespace;
        if (128 < strlen($namespace)) {
            throw new \InvalidArgumentException(sprintf('%s expects a prefix key of no longer than 128 characters', __METHOD__));
        }
        return parent::setNamespace($namespace);
    }

    /**
     * @param $namespaceDelimiter
     * @return $this
     */
    public function setNamespaceDelimiter($namespaceDelimiter)
    {
        $namespaceDelimiter = (string)$namespaceDelimiter;
        if ($this->namespaceDelimiter !== $namespaceDelimiter) {
            $this->namespaceDelimiter = $namespaceDelimiter;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceDelimiter()
    {
        return $this->namespaceDelimiter;
    }

    /**
     * @param MemcacheResourceManager|null $resourceManager
     * @return $this
     */
    public function setResourceManager(MemcacheResourceManager $resourceManager = null)
    {
        if ($this->resourceManager !== $resourceManager) {
            $this->resourceManager = $resourceManager;
        }
        return $this;
    }

    /**
     * @return MemcacheResourceManager
     */
    public function getResourceManager()
    {
        if (!$this->resourceManager) {
            $this->resourceManager = new MemcacheResourceManager();
        }
        return $this->resourceManager;
    }

    /**
     * @return string
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * @param $resourceId
     * @return $this
     */
    public function setResourceId($resourceId)
    {
        $resourceId = (string)$resourceId;
        if ($this->resourceId !== $resourceId) {
            $this->resourceId = $resourceId;
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getCompression()
    {
        return $this->compression;
    }

    /**
     * @param $compression
     * @return $this
     */
    public function setCompression($compression)
    {
        $compression = (bool)$compression;
        if ($this->compression !== $compression) {
            $this->compression = $compression;
        }
        return $this;
    }

    /**
     * @param $servers
     * @return $this
     */
    public function setServers($servers)
    {
        $this->getResourceManager()->addServers($this->getResourceId(), $servers);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServers()
    {
        return $this->getResourceManager()->getServers($this->getResourceId());
    }

    /**
     * @param $threshold
     * @return $this
     */
    public function setAutoCompressThreshold($threshold)
    {
        $this->getResourceManager()->setAutoCompressThreshold($this->getResourceId(), $threshold);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAutoCompressThreshold()
    {
        return $this->getResourceManager()->getAutoCompressThreshold($this->getResourceId());
    }

    /**
     * @param $minSavings
     * @return $this
     */
    public function setAutoCompressMinSavings($minSavings)
    {
        $this->getResourceManager()->setAutoCompressMinSavings($this->getResourceId(), $minSavings);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAutoCompressMinSavings()
    {
        return $this->getResourceManager()->getAutoCompressMinSavings($this->getResourceId());
    }

    /**
     * @param array $serverDefaults
     * @return $this
     */
    public function setServerDefaults(array $serverDefaults)
    {
        $this->getResourceManager()->setServerDefaults($this->getResourceId(), $serverDefaults);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServerDefaults()
    {
        return $this->getResourceManager()->getServerDefaults($this->getResourceId());
    }

    /**
     * @param $callback
     * @return $this
     */
    public function setFailureCallback($callback)
    {
        $this->getResourceManager()->setFailureCallback($this->getResourceId(), $callback);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFailureCallback()
    {
        return $this->getResourceManager()->getFailureCallback($this->getResourceId());
    }
}
