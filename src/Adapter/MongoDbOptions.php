<?php

namespace Ions\Cache\Adapter;

/**
 * Class MongoDbOptions
 * @package Ions\Cache\Adapter
 */
class MongoDbOptions extends AdapterOptions
{
    /**
     * @var string
     */
    private $namespaceDelimiter = ':';
    /**
     * @var
     */
    private $resourceManager;
    /**
     * @var string
     */
    private $resourceId = 'default';

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
     * @param MongoDbResourceManager|null $resourceManager
     * @return $this
     */
    public function setResourceManager(MongoDbResourceManager $resourceManager = null)
    {
        if ($this->resourceManager !== $resourceManager) {
            $this->resourceManager = $resourceManager;
        }
        return $this;
    }

    /**
     * @return MongoDbResourceManager
     */
    public function getResourceManager()
    {
        return $this->resourceManager ?: $this->resourceManager = new MongoDbResourceManager();
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
     * @param $server
     * @return $this
     */
    public function setServer($server)
    {
        $this->getResourceManager()->setServer($this->getResourceId(), $server);
        return $this;
    }

    /**
     * @param array $connectionOptions
     * @return $this
     */
    public function setConnectionOptions(array $connectionOptions)
    {
        $this->getResourceManager()->setConnectionOptions($this->getResourceId(), $connectionOptions);
        return $this;
    }

    /**
     * @param array $driverOptions
     * @return $this
     */
    public function setDriverOptions(array $driverOptions)
    {
        $this->getResourceManager()->setDriverOptions($this->getResourceId(), $driverOptions);
        return $this;
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
     * @param $collection
     * @return $this
     */
    public function setCollection($collection)
    {
        $this->getResourceManager()->setCollection($this->getResourceId(), $collection);
        return $this;
    }
}
