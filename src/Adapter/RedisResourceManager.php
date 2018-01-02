<?php

namespace Ions\Cache\Adapter;

use Redis as RedisResource;
use ReflectionClass;
use Traversable;

/**
 * Class RedisResourceManager
 * @package Ions\Cache\Adapter
 */
class RedisResourceManager
{
    /**
     * @var array
     */
    protected $resources = [];

    /**
     * @param $id
     * @return bool
     */
    public function hasResource($id)
    {
        return isset($this->resources[$id]);
    }

    /**
     * @param $resourceId
     * @return mixed
     */
    public function getVersion($resourceId)
    {
        $this->getResource($resourceId);
        return $this->resources[$resourceId]['version'];
    }

    /**
     * @param $resourceId
     * @return int
     */
    public function getMajorVersion($resourceId)
    {
        $this->getResource($resourceId);
        return (int)$this->resources[$resourceId]['version'];
    }

    /**
     * @param $id
     * @return int
     */
    public function getMayorVersion($id)
    {
        return $this->getMajorVersion($id);
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getDatabase($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        return $resource['database'];
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getPassword($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        return $resource['password'];
    }

    /**
     * @param $id
     * @return RedisResource
     * @throws \RuntimeException
     */
    public function getResource($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        if ($resource['resource'] instanceof RedisResource) {
            if (!$resource['initialized']) {
                $this->connect($resource);
            }
            if (!$resource['version']) {
                $info = $resource['resource']->info();
                $resource['version'] = $info['redis_version'];
            }
            return $resource['resource'];
        }
        $redis = new RedisResource();
        $resource['resource'] = $redis;
        $this->connect($resource);
        foreach ($resource['lib_options'] as $k => $v) {
            $redis->setOption($k, $v);
        }
        $info = $redis->info();
        $resource['version'] = $info['redis_version'];
        $this->resources[$id]['resource'] = $redis;
        return $redis;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getServer($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        return $resource['server'];
    }

    /**
     * @param $server
     * @throws \InvalidArgumentException
     */
    protected function normalizeServer(&$server)
    {
        $host = null;
        $port = null;
        $timeout = 0;

        if (is_array($server)) {
            if (isset($server[0])) {
                $host = (string)$server[0];
                $port = isset($server[1]) ? (int)$server[1] : $port;
                $timeout = isset($server[2]) ? (int)$server[2] : $timeout;
            }
            if (!isset($server[0]) && isset($server['host'])) {
                $host = (string)$server['host'];
                $port = isset($server['port']) ? (int)$server['port'] : $port;
                $timeout = isset($server['timeout']) ? (int)$server['timeout'] : $timeout;
            }
        } else {
            $server = trim($server);
            if (strpos($server, '/') !== 0) {
                $server = parse_url($server);
            } else {
                $server = ['host' => $server];
            }
            if (!$server) {
                throw new \InvalidArgumentException('Invalid server given');
            }
            $host = $server['host'];
            $port = isset($server['port']) ? (int)$server['port'] : $port;
            $timeout = isset($server['timeout']) ? (int)$server['timeout'] : $timeout;
        }
        if (!$host) {
            throw new \InvalidArgumentException('Missing required server host');
        }
        $server = ['host' => $host, 'port' => $port, 'timeout' => $timeout,];
    }

    /**
     * @param $resource
     * @param $serverUri
     * @return null|void
     */
    protected function extractPassword($resource, $serverUri)
    {
        if (!empty($resource['password'])) {
            return $resource['password'];
        }
        if (!is_string($serverUri)) {
            return;
        }
        $server = trim($serverUri);
        if (strpos($server, '/') === 0) {
            return;
        }
        $server = parse_url($server);
        return isset($server['pass']) ? $server['pass'] : null;
    }

    /**
     * @param array $resource
     * @throws \RuntimeException
     */
    protected function connect(array & $resource)
    {
        $server = $resource['server'];
        $redis = $resource['resource'];
        if ($resource['persistent_id'] !== '') {
            $success = $redis->pconnect($server['host'], $server['port'], $server['timeout'], $resource['persistent_id']);
        } elseif ($server['port']) {
            $success = $redis->connect($server['host'], $server['port'], $server['timeout']);
        } elseif ($server['timeout']) {
            $success = $redis->connect($server['host'], $server['timeout']);
        } else {
            $success = $redis->connect($server['host']);
        }
        if (!$success) {
            throw new \RuntimeException('Could not estabilish connection with Redis instance');
        }
        $resource['initialized'] = true;
        if ($resource['password']) {
            $redis->auth($resource['password']);
        }
        $redis->select($resource['database']);
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
        $defaults = ['persistent_id' => '', 'lib_options' => [], 'server' => [], 'password' => '', 'database' => 0, 'resource' => null, 'initialized' => false, 'version' => 0,];
        if (!$resource instanceof RedisResource) {

            if (!is_array($resource)) {
                throw new \InvalidArgumentException('Resource must be an instance of an array or Traversable');
            }

            $resource = array_merge($defaults, $resource);
            $this->normalizePersistentId($resource['persistent_id']);
            $this->normalizeLibOptions($resource['lib_options']);
            $resource['password'] = $this->extractPassword($resource, $resource['server']);
            $this->normalizeServer($resource['server']);
        } else {
            $resource = array_merge($defaults, ['resource' => $resource, 'initialized' => isset($resource->socket),]);
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
     * @return $this|RedisResourceManager
     * @throws \RuntimeException
     */
    public function setPersistentId($id, $persistentId)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['persistent_id' => $persistentId]);
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof RedisResource) {
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
        if ($resource instanceof RedisResource) {
            throw new \RuntimeException("Can't get persistent id of an instantiated redis resource");
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
     * @return $this|RedisResourceManager
     */
    public function setLibOptions($id, array $libOptions)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['lib_options' => $libOptions]);
        }
        $this->normalizeLibOptions($libOptions);
        $resource = &$this->resources[$id];
        $resource['lib_options'] = $libOptions;
        if ($resource['resource'] instanceof RedisResource) {
            $redis = &$resource['resource'];
            if (method_exists($redis, 'setOptions')) {
                $redis->setOptions($libOptions);
            } else {
                foreach ($libOptions as $key => $value) {
                    $redis->setOption($key, $value);
                }
            }
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
        if ($resource instanceof RedisResource) {
            $libOptions = [];
            $reflection = new ReflectionClass('Redis');
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
     * @return RedisResourceManager
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
        if ($resource instanceof RedisResource) {
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
            $const = 'Redis::OPT_' . str_replace([' ', '-'], '_', strtoupper($key));
            if (!defined($const)) {
                throw new \InvalidArgumentException("Unknown redis option '{$key}' ({$const})");
            }
            $key = constant($const);
        } else {
            $key = (int)$key;
        }
    }

    /**
     * @param $id
     * @param $server
     * @return $this|RedisResourceManager
     */
    public function setServer($id, $server)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['server' => $server]);
        }
        $this->normalizeServer($server);
        $resource = &$this->resources[$id];
        $resource['password'] = $this->extractPassword($resource, $server);
        if ($resource['resource'] instanceof RedisResource) {
            $resourceParams = ['server' => $server];
            if (!empty($resource['password'])) {
                $resourceParams['password'] = $resource['password'];
            }
            $this->setResource($id, $resourceParams);
        } else {
            $resource['server'] = $server;
        }
        return $this;
    }

    /**
     * @param $id
     * @param $password
     * @return $this|RedisResourceManager
     */
    public function setPassword($id, $password)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['password' => $password,]);
        }
        $resource = &$this->resources[$id];
        $resource['password'] = $password;
        $resource['initialized'] = false;
        return $this;
    }

    /**
     * @param $id
     * @param $database
     * @return $this|RedisResourceManager
     */
    public function setDatabase($id, $database)
    {
        $database = (int)$database;
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['database' => $database,]);
        }
        $resource = &$this->resources[$id];
        if ($resource['resource'] instanceof RedisResource && $resource['initialized']) {
            $resource['resource']->select($database);
        }
        $resource['database'] = $database;
        return $this;
    }
}
