<?php

namespace Ions\Cache\Adapter;

/**
 * Class MemcachedOptions
 * @package Ions\Cache\Adapter
 */
class MemcachedOptions extends AdapterOptions
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
     * @param MemcachedResourceManager|null $resourceManager
     * @return $this
     */
    public function setResourceManager(MemcachedResourceManager $resourceManager = null)
    {
        if ($this->resourceManager !== $resourceManager) {
            $this->resourceManager = $resourceManager;
        }
        return $this;
    }

    /**
     * @return MemcachedResourceManager
     */
    public function getResourceManager()
    {
        if (!$this->resourceManager) {
            $this->resourceManager = new MemcachedResourceManager();
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
     * @return mixed
     */
    public function getPersistentId()
    {
        return $this->getResourceManager()->getPersistentId($this->getResourceId());
    }

    /**
     * @param $persistentId
     * @return $this
     */
    public function setPersistentId($persistentId)
    {
        $this->getResourceManager()->setPersistentId($this->getResourceId(), $persistentId);
        return $this;
    }

    /**
     * @param $servers
     * @return $this
     */
    public function setServers($servers)
    {
        $this->getResourceManager()->setServers($this->getResourceId(), $servers);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServers()
    {
        return $this->getResourceManager()->getServers($this->getResourceId());
    }
}
