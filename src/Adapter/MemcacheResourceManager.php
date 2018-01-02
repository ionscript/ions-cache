<?php

namespace Ions\Cache\Adapter;

use ArrayAccess;
use Memcache as MemcacheResource;
use Traversable;

/**
 * Class MemcacheResourceManager
 * @package Ions\Cache\Adapter
 */
class MemcacheResourceManager
{
    /**
     * @var array
     */
    protected $resources = [];

    /**
     * @var array
     */
    protected $serverDefaults = [];

    /**
     * @var array
     */
    protected $failureCallbacks = [];

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
     * @return MemcacheResource|mixed
     * @throws \RuntimeException
     */
    public function getResource($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = $this->resources[$id];
        if ($resource instanceof MemcacheResource) {
            return $resource;
        }
        $memc = new MemcacheResource();
        $this->setResourceAutoCompressThreshold($memc, $resource['auto_compress_threshold'], $resource['auto_compress_min_savings']);
        foreach ($resource['servers'] as $server) {
            $this->addServerToResource($memc, $server, $this->serverDefaults[$id], $this->failureCallbacks[$id]);
        }
        $this->resources[$id] = $memc;
        return $memc;
    }

    /**
     * @param $id
     * @param $resource
     * @param null $failureCallback
     * @param array $serverDefaults
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setResource($id, $resource, $failureCallback = null, $serverDefaults = [])
    {
        $id = (string)$id;
        if (!is_array($serverDefaults)) {
            throw new \InvalidArgumentException('ServerDefaults must be an instance Traversable or an array');
        }

        if (!($resource instanceof MemcacheResource)) {
            if (!is_array($resource)) {
                throw new \InvalidArgumentException('Resource must be an instance of Memcache or an array or Traversable');
            }

            if (isset($resource['server_defaults'])) {
                $serverDefaults = array_merge($serverDefaults, $resource['server_defaults']);
                unset($resource['server_defaults']);
            }

            $resourceOptions = ['servers' => [], 'auto_compress_threshold' => null, 'auto_compress_min_savings' => null,];
            $resource = array_merge($resourceOptions, $resource);
            $this->normalizeAutoCompressThreshold($resource['auto_compress_threshold'], $resource['auto_compress_min_savings']);
            $this->normalizeServers($resource['servers']);
        }

        $this->normalizeServerDefaults($serverDefaults);
        $this->resources[$id] = $resource;
        $this->failureCallbacks[$id] = $failureCallback;
        $this->serverDefaults[$id] = $serverDefaults;

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
     * @param $threshold
     * @param $minSavings
     */
    protected function normalizeAutoCompressThreshold(& $threshold, & $minSavings)
    {
        if (is_array($threshold) || ($threshold instanceof ArrayAccess)) {
            $tmpThreshold = (isset($threshold['threshold'])) ? $threshold['threshold'] : null;
            $minSavings = (isset($threshold['min_savings'])) ? $threshold['min_savings'] : $minSavings;
            $threshold = $tmpThreshold;
        }

        if (isset($threshold)) {
            $threshold = (int)$threshold;
        }

        if (isset($minSavings)) {
            $minSavings = (float)$minSavings;
        }
    }

    /**
     * @param MemcacheResource $resource
     * @param $threshold
     * @param $minSavings
     */
    protected function setResourceAutoCompressThreshold(MemcacheResource $resource, $threshold, $minSavings)
    {
        if (!isset($threshold)) {
            return;
        }

        if (isset($minSavings)) {
            $resource->setCompressThreshold($threshold, $minSavings);
        } else {
            $resource->setCompressThreshold($threshold);
        }
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getAutoCompressThreshold($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }

        $resource = &$this->resources[$id];

        if ($resource instanceof MemcacheResource) {
            throw new \RuntimeException('Cannot get compress threshold once resource is created');
        }

        return $resource['auto_compress_threshold'];
    }

    /**
     * @param $id
     * @param $threshold
     * @param bool $minSavings
     * @return $this|MemcacheResourceManager
     */
    public function setAutoCompressThreshold($id, $threshold, $minSavings = false)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['auto_compress_threshold' => $threshold,]);
        }
        $this->normalizeAutoCompressThreshold($threshold, $minSavings);
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcacheResource) {
            $this->setResourceAutoCompressThreshold($resource, $threshold, $minSavings);
        } else {
            $resource['auto_compress_threshold'] = $threshold;
            if ($minSavings !== false) {
                $resource['auto_compress_min_savings'] = $minSavings;
            }
        }
        return $this;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getAutoCompressMinSavings($id)
    {
        if (!$this->hasResource($id)) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcacheResource) {
            throw new \RuntimeException('Cannot get compress min savings once resource is created');
        }
        return $resource['auto_compress_min_savings'];
    }

    /**
     * @param $id
     * @param $minSavings
     * @return $this|MemcacheResourceManager
     * @throws \RuntimeException
     */
    public function setAutoCompressMinSavings($id, $minSavings)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['auto_compress_min_savings' => $minSavings,]);
        }
        $minSavings = (float)$minSavings;
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcacheResource) {
            throw new \RuntimeException('Cannot set compress min savings without a threshold value once a resource is created');
        } else {
            $resource['auto_compress_min_savings'] = $minSavings;
        }
        return $this;
    }

    /**
     * @param $id
     * @param array $serverDefaults
     * @return $this|MemcacheResourceManager
     */
    public function setServerDefaults($id, array $serverDefaults)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['server_defaults' => $serverDefaults]);
        }
        $this->normalizeServerDefaults($serverDefaults);
        $this->serverDefaults[$id] = $serverDefaults;
        return $this;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getServerDefaults($id)
    {
        if (!isset($this->serverDefaults[$id])) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        return $this->serverDefaults[$id];
    }

    /**
     * @param $serverDefaults
     * @throws \InvalidArgumentException
     */
    protected function normalizeServerDefaults(& $serverDefaults)
    {
        if (!is_array($serverDefaults) && !($serverDefaults instanceof Traversable)) {
            throw new \InvalidArgumentException('Server defaults must be an array or an instance of Traversable');
        }
        $result = ['persistent' => true, 'weight' => 1, 'timeout' => 1, 'retry_interval' => 15,];
        foreach ($serverDefaults as $key => $value) {
            switch ($key) {
                case 'persistent':
                    $value = (bool)$value;
                    break;
                case 'weight':
                case 'timeout':
                case 'retry_interval':
                    $value = (int)$value;
                    break;
            }
            $result[$key] = $value;
        }
        $serverDefaults = $result;
    }

    /**
     * @param $id
     * @param $failureCallback
     * @return $this|MemcacheResourceManager
     */
    public function setFailureCallback($id, $failureCallback)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, [], $failureCallback);
        }
        $this->failureCallbacks[$id] = $failureCallback;
        return $this;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \RuntimeException
     */
    public function getFailureCallback($id)
    {
        if (!isset($this->failureCallbacks[$id])) {
            throw new \RuntimeException("No resource with id '{$id}'");
        }
        return $this->failureCallbacks[$id];
    }

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
        if ($resource instanceof MemcacheResource) {
            throw new \RuntimeException('Cannot get server list once resource is created');
        }
        return $resource['servers'];
    }

    /**
     * @param $id
     * @param $servers
     * @return $this|MemcacheResourceManager
     */
    public function addServers($id, $servers)
    {
        if (!$this->hasResource($id)) {
            return $this->setResource($id, ['servers' => $servers]);
        }
        $this->normalizeServers($servers);
        $resource = &$this->resources[$id];
        if ($resource instanceof MemcacheResource) {
            foreach ($servers as $server) {
                $this->addServerToResource($resource, $server, $this->serverDefaults[$id], $this->failureCallbacks[$id]);
            }
        } else {
            $resource['servers'] = array_merge($resource['servers'], array_udiff($servers, $resource['servers'], [$this, 'compareServers']));
        }
        return $this;
    }

    /**
     * @param $id
     * @param $server
     * @return MemcacheResourceManager
     */
    public function addServer($id, $server)
    {
        return $this->addServers($id, [$server]);
    }

    protected function addServerToResource(MemcacheResource $resource, array $server, array $serverDefaults, $failureCallback)
    {
        $server = array_merge($serverDefaults, $server);
        $params = [$server['host'], $server['port'], $server['persistent'], $server['weight'], $server['timeout'], $server['retry_interval'], $server['status'],];
        if (isset($failureCallback)) {
            $params[] = $failureCallback;
        }
        call_user_func_array([$resource, 'addServer'], $params);
    }

    /**
     * @param $servers
     */
    protected function normalizeServers(& $servers)
    {
        if (is_string($servers)) {
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
     * @param $server
     * @throws \InvalidArgumentException
     */
    protected function normalizeServer(& $server)
    {
        $sTmp = ['host' => null, 'port' => 11211, 'weight' => null, 'status' => true, 'persistent' => null, 'timeout' => null, 'retry_interval' => null,];

        if (is_array($server)) {
            if (isset($server[0])) {
                $server = array_combine(array_slice(array_keys($sTmp), 0, count($server)), $server);
            }
            $sTmp = array_merge($sTmp, $server);
        } elseif (is_string($server)) {
            $server = trim($server);
            if (strpos($server, '://') === false) {
                $server = 'tcp://' . $server;
            }
            $urlParts = parse_url($server);
            if (!$urlParts) {
                throw new \InvalidArgumentException("Invalid server given");
            }
            $sTmp = array_merge($sTmp, array_intersect_key($urlParts, $sTmp));
            if (isset($urlParts['query'])) {
                $query = null;
                parse_str($urlParts['query'], $query);
                $sTmp = array_merge($sTmp, array_intersect_key($query, $sTmp));
            }
        }
        if (!$sTmp['host']) {
            throw new \InvalidArgumentException('Missing required server host');
        }
        foreach ($sTmp as $key => $value) {
            if (isset($value)) {
                switch ($key) {
                    case 'host':
                        $value = (string)$value;
                        break;
                    case 'status':
                    case 'persistent':
                        $value = (bool)$value;
                        break;
                    case 'port':
                    case 'weight':
                    case 'timeout':
                    case 'retry_interval':
                        $value = (int)$value;
                        break;
                }
            }
            $sTmp[$key] = $value;
        }
        $sTmp = array_filter($sTmp, function ($val) {
            return isset($val);
        });
        $server = $sTmp;
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
