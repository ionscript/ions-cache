<?php

namespace Ions\Cache\Adapter;

/**
 * Class RedisOptions
 * @package Ions\Cache\Adapter
 */
class RedisOptions extends AdapterOptions
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
     * @param RedisResourceManager|null $resourceManager
     * @return $this
     */
    public function setResourceManager(RedisResourceManager $resourceManager = null)
    {
        if ($this->resourceManager !== $resourceManager) {
            $this->resourceManager = $resourceManager;
        }
        return $this;
    }

    /**
     * @return RedisResourceManager
     */
    public function getResourceManager()
    {
        if (!$this->resourceManager) {
            $this->resourceManager = new RedisResourceManager();
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
     * @param array $libOptions
     * @return $this
     */
    public function setLibOptions(array $libOptions)
    {
        $this->getResourceManager()->setLibOptions($this->getResourceId(), $libOptions);
        return $this;
    }

    /**
     * @return array
     */
    public function getLibOptions()
    {
        return $this->getResourceManager()->getLibOptions($this->getResourceId());
    }

    /**
     * @param $server
     * @return $this
     */
    public function setServer($server)
    {
        $this->getResourceManager()->setServer($this->getResourceId(), $server);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getServer()
    {
        return $this->getResourceManager()->getServer($this->getResourceId());
    }

    /**
     * @param $database
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->getResourceManager()->setDatabase($this->getResourceId(), $database);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->getResourceManager()->getDatabase($this->getResourceId());
    }

    /**
     * @param $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->getResourceManager()->setPassword($this->getResourceId(), $password);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->getResourceManager()->getPassword($this->getResourceId());
    }
}
