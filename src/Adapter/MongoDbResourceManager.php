<?php

namespace Ions\Cache\Adapter;

use MongoCollection;
use MongoException;

/**
 * Class MongoDbResourceManager
 * @package Ions\Cache\Adapter
 */
class MongoDbResourceManager
{
    /**
     * @var array
     */
    private $resources = [];

    /**
     * @param $id
     * @return bool
     */
    public function hasResource($id)
    {
        return isset($this->resources[$id]);
    }

    /**
     * @param $id
     * @param $resource
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setResource($id, $resource)
    {
        if ($resource instanceof MongoCollection) {
            $this->resources[$id] = ['db' => (string)$resource->db, 'db_instance' => $resource->db, 'collection' => (string)$resource, 'collection_instance' => $resource,];
            return $this;
        }
        if (!is_array($resource)) {
            throw new \InvalidArgumentException(sprintf('%s expects an array or MongoCollection; received %s', __METHOD__, (is_object($resource) ? get_class($resource) : gettype($resource))));
        }
        $this->resources[$id] = $resource;
        return $this;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getResource($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = $this->resources[$id];
        if (!isset($resource['collection_instance'])) {
            try {
                if (!isset($resource['db_instance'])) {
                    if (!isset($resource['client_instance'])) {
                        $clientClass = version_compare(phpversion('mongo'), '1.3.0', '<') ? 'Mongo' : 'MongoClient';
                        $resource['client_instance'] = new $clientClass(isset($resource['server']) ? $resource['server'] : null, isset($resource['connection_options']) ? $resource['connection_options'] : [], isset($resource['driver_options']) ? $resource['driver_options'] : []);
                    }
                    $resource['db_instance'] = $resource['client_instance']->selectDB(isset($resource['db']) ? $resource['db'] : '');
                }
                $collection = $resource['db_instance']->selectCollection(isset($resource['collection']) ? $resource['collection'] : '');
                $collection->ensureIndex(['key' => 1]);
                $this->resources[$id]['collection_instance'] = $collection;
            } catch (MongoException $e) {
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }
        return $this->resources[$id]['collection_instance'];
    }

    /**
     * @param $id
     * @param $server
     */
    public function setServer($id, $server)
    {
        $this->resources[$id]['server'] = (string)$server;
        unset($this->resource[$id]['client_instance']);
        unset($this->resource[$id]['db_instance']);
        unset($this->resource[$id]['collection_instance']);
    }

    /**
     * @param $id
     * @return null
     * @throws \RuntimeException
     */
    public function getServer($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        return isset($this->resources[$id]['server']) ? $this->resources[$id]['server'] : null;
    }

    /**
     * @param $id
     * @param array $connectionOptions
     */
    public function setConnectionOptions($id, array $connectionOptions)
    {
        $this->resources[$id]['connection_options'] = $connectionOptions;
        unset($this->resource[$id]['client_instance']);
        unset($this->resource[$id]['db_instance']);
        unset($this->resource[$id]['collection_instance']);
    }

    /**
     * @param $id
     * @return array
     * @throws \RuntimeException
     */
    public function getConnectionOptions($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        return isset($this->resources[$id]['connection_options']) ? $this->resources[$id]['connection_options'] : [];
    }

    /**
     * @param $id
     * @param array $driverOptions
     */
    public function setDriverOptions($id, array $driverOptions)
    {
        $this->resources[$id]['driver_options'] = $driverOptions;
        unset($this->resource[$id]['client_instance']);
        unset($this->resource[$id]['db_instance']);
        unset($this->resource[$id]['collection_instance']);
    }

    /**
     * @param $id
     * @return array
     * @throws \RuntimeException
     */
    public function getDriverOptions($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        return isset($this->resources[$id]['driver_options']) ? $this->resources[$id]['driver_options'] : [];
    }

    /**
     * @param $id
     * @param $database
     */
    public function setDatabase($id, $database)
    {
        $this->resources[$id]['db'] = (string)$database;
        unset($this->resource[$id]['db_instance']);
        unset($this->resource[$id]['collection_instance']);
    }

    /**
     * @param $id
     * @return string
     * @throws \RuntimeException
     */
    public function getDatabase($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        return isset($this->resources[$id]['db']) ? $this->resources[$id]['db'] : '';
    }

    /**
     * @param $id
     * @param $collection
     */
    public function setCollection($id, $collection)
    {
        $this->resources[$id]['collection'] = (string)$collection;
        unset($this->resource[$id]['collection_instance']);
    }

    /**
     * @param $id
     * @return string
     * @throws \RuntimeException
     */
    public function getCollection($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        return isset($this->resources[$id]['collection']) ? $this->resources[$id]['collection'] : '';
    }
}
