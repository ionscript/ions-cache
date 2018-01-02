<?php

namespace Ions\Cache\Adapter;

use Memcached as MemcachedResource;
use ReflectionClass;
use Traversable;

/**
 * Class MemcachedResourceManager
 * @package Ions\Cache\Adapter
 */
class MemcachedResourceManager
{
    /**
     * @var array
     */
    protected $resources = [];

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getServers($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            return $resource->getServerList();
        }
        return $resource['servers'];
    }

    /**
     * @param $server
     * @throws \InvalidArgumentException
     */
    protected function normalizeServer(&$server)
    {
        $host = null;
        $port = 11211;
        $weight = 0;

        if (is_array($server)) {
            if (isset($server[0])) {
                $host = (string)$server[0];
                $port = isset($server[1]) ? (int)$server[1] : $port;
                $weight = isset($server[2]) ? (int)$server[2] : $weight;
            }
            if (!isset($server[0]) && isset($server['host'])) {
                $host = (string)$server['host'];
                $port = isset($server['port']) ? (int)$server['port'] : $port;
                $weight = isset($server['weight']) ? (int)$server['weight'] : $weight;
            }
        } else {
            $server = trim($server);
            if (strpos($server, '://') === false) {
                $server = 'tcp://' . $server;
            }
            $server = parse_url($server);
            if (!$server) {
                throw new \InvalidArgumentException('Invalid server given');
            }
            $host = $server['host'];
            $port = isset($server['port']) ? (int)$server['port'] : $port;
            if (isset($server['query'])) {
                $query = null;
                parse_str($server['query'], $query);
                if (isset($query['weight'])) {
                    $weight = (int)$query['weight'];
                }
            }
        }
        if (!$host) {
            throw new \InvalidArgumentException('Missing required server host');
        }
        $server = ['host' => $host, 'port' => $port, 'weight' => $weight,];
    }

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
     * @return MemcachedResource|mixed
     * @throws \RuntimeException
     */
    public function getResource($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = $this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            return $resource;
        }
        if ($resource['persistent_id'] !== '') {
            $memc = new MemcachedResource($resource['persistent_id']);
        } else {
            $memc = new MemcachedResource();
        }
        if (method_exists($memc, 'setOptions')) {
            $memc->setOptions($resource['lib_options']);
        } else {
            foreach ($resource['lib_options'] as $k => $v) {
                $memc->setOption($k, $v);
            }
        }
        $servers = array_udiff($resource['servers'], $memc->getServerList(), [$this, 'compareServers']);
        if ($servers) {
            $memc->addServers(array_values(array_map('array_values', $servers)));
        }
        $this->resources[$id] = $memc;
        return $memc;
    }

    /**
     * @param $id
     * @param $resource
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setResource($id, $resource)
    {
        $id = (string)$id;
        if (!($resource instanceof MemcachedResource)) {

            if (!is_array($resource)) {
                throw new \InvalidArgumentException('Resource must be an instance of Memcached or an array or Traversable');
            }

            $resource = array_merge(['persistent_id' => '', 'lib_options' => [], 'servers' => [],], $resource);
            $this->normalizePersistentId($resource['persistent_id']);
            $this->normalizeLibOptions($resource['lib_options']);
            $this->normalizeServers($resource['servers']);
        }

        $this->resources[$id] = $resource;
        return $this;

    }

    /**
     * @param $id
     * @return $this
     */
    public function removeResource($id)
    {
        unset($this->resources[$id]);
        return $this;
    }

    /**
     * @param $id
     * @param $persistentId
     * @return $this|MemcachedResourceManager
     * @throws \RuntimeException
     */
    public function setPersistentId($id, $persistentId)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['persistent_id' => $persistentId]);
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            throw new \RuntimeException("Can't change persistent id of resource {$id} after instanziation");
        }
        $this->normalizePersistentId($persistentId);
        $resource['persistent_id'] = $persistentId;
        return $this;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getPersistentId($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            throw new \RuntimeException("Can't get persistent id of an instantiated memcached resource");
        }
        return $resource['persistent_id'];
    }

    /**
     * @param $persistentId
     */
    protected function normalizePersistentId(& $persistentId)
    {
        $persistentId = (string)$persistentId;
    }

    /**
     * @param $id
     * @param array $libOptions
     * @return $this|MemcachedResourceManager
     */
    public function setLibOptions($id, array $libOptions)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['lib_options' => $libOptions]);
        }
        $this->normalizeLibOptions($libOptions);
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            if (method_exists($resource, 'setOptions')) {
                $resource->setOptions($libOptions);
            } else {
                foreach ($libOptions as $key => $value) {
                    $resource->setOption($key, $value);
                }
            }
        } else {
            $resource['lib_options'] = $libOptions;
        }
        return $this;
    }

    /**
     * @param $id
     * @return array
     * @throws \RuntimeException
     */
    public function getLibOptions($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            $libOptions = [];
            $reflection = new ReflectionClass('Memcached');
            $constants = $reflection->getConstants();
            foreach ($constants as $constName => $constValue) {
                if (strpos($constName, 'OPT_') === 0) {
                    $libOptions[$constValue] = $resource->getOption($constValue);
                }
            }
            return $libOptions;
        }
        return $resource['lib_options'];
    }

    /**
     * @param $id
     * @param $key
     * @param $value
     * @return MemcachedResourceManager
     */
    public function setLibOption($id, $key, $value)
    {
        return $this->setLibOptions($id, [$key => $value]);
    }

    /**
     * @param $id
     * @param $key
     * @return null
     * @throws \RuntimeException
     */
    public function getLibOption($id, $key)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $this->normalizeLibOptionKey($key);
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            return $resource->getOption($key);
        }
        return isset($resource['lib_options'][$key]) ? $resource['lib_options'][$key] : null;
    }

    /**
     * @param $libOptions
     * @throws \InvalidArgumentException
     */
    protected function normalizeLibOptions(& $libOptions)
    {
        if (!is_array($libOptions) && !($libOptions instanceof Traversable)) {
            throw new \InvalidArgumentException('Lib-Options must be an array or an instance of Traversable');
        }
        $result = [];
        foreach ($libOptions as $key => $value) {
            $this->normalizeLibOptionKey($key);
            $result[$key] = $value;
        }
        $libOptions = $result;
    }

    /**
     * @param $key
     * @throws \InvalidArgumentException
     */
    protected function normalizeLibOptionKey(& $key)
    {
        if (is_string($key)) {
            $const = 'Memcached::OPT_' . str_replace([' ', '-'], '_', strtoupper($key));
            if (!defined($const)) {
                throw new \InvalidArgumentException("Unknown libmemcached option '{$key}' ({$const})");
            }
            $key = constant($const);
        } else {
            $key = (int)$key;
        }
    }

    /**
     * @param $id
     * @param $servers
     * @return $this|MemcachedResourceManager
     */
    public function setServers($id, $servers)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['servers' => $servers]);
        }
        $this->normalizeServers($servers);
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            $servers = array_udiff($servers, $resource->getServerList(), [$this, 'compareServers']);
            if ($servers) {
                $resource->addServers($servers);
            }
        } else {
            $resource['servers'] = $servers;
        }
        return $this;
    }

    /**
     * @param $id
     * @param $servers
     * @return $this|MemcachedResourceManager
     */
    public function addServers($id, $servers)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['servers' => $servers]);
        }
        $this->normalizeServers($servers);
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcachedResource) {
            $servers = array_udiff($servers, $resource->getServerList(), [$this, 'compareServers']);
            if ($servers) {
                $resource->addServers($servers);
            }
        } else {
            $resource['servers'] = array_merge($resource['servers'], array_udiff($servers, $resource['servers'], [$this, 'compareServers']));
        }
        return $this;
    }

    /**
     * @param $id
     * @param $server
     * @return MemcachedResourceManager
     */
    public function addServer($id, $server)
    {
        return $this->addServers($id, [$server]);
    }

    /**
     * @param $servers
     */
    protected function normalizeServers(& $servers)
    {
        if (!is_array($servers) && !$servers instanceof Traversable) {
            $servers = explode(',', $servers);
        }
        $result = [];
        foreach ($servers as $server) {
            $this->normalizeServer($server);
            $result[$server['host'] . ':' . $server['port']] = $server;
        }
        $servers = array_values($result);
    }

    /**
     * @param array $serverA
     * @param array $serverB
     * @return int
     */
    protected function compareServers(array $serverA, array $serverB)
    {
        $keyA = $serverA['host'] . ':' . $serverA['port'];
        $keyB = $serverB['host'] . ':' . $serverB['port'];
        if ($keyA === $keyB) {
            return 0;
        }
        return $keyA > $keyB ? 1 : -1;
    }
}
