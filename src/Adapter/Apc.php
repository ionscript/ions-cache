<?php

namespace Ions\Cache\Adapter;

use APCIterator as BaseApcIterator;

/**
 * Class Apc
 * @package Ions\Cache\Adapter
 */
class Apc extends AbstractAdapter
{
    /**
     * @var
     */
    protected $totalSpace;

    /**
     * Apc constructor.
     * @param null $options
     * @throws \RuntimeException
     */
    public function __construct($options = null)
    {
        if (!extension_loaded('apc')) {
            throw new \RuntimeException('Missing ext/apc');
        }
        $enabled = ini_get('apc.enabled');
        if (PHP_SAPI === 'cli') {
            $enabled = $enabled && (bool)ini_get('apc.enable_cli');
        }
        if (!$enabled) {
            throw new \RuntimeException("ext/apc is disabled - see 'apc.enabled' and 'apc.enable_cli'");
        }
        parent::__construct($options);
    }

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (!$options instanceof ApcOptions) {
            $options = new ApcOptions($options);
        }
        return parent::setOptions($options);
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new ApcOptions());
        }
        return $this->options;
    }

    /**
     * @return mixed
     */
    public function getTotalSpace()
    {
        if ($this->totalSpace === null) {
            $smaInfo = apc_sma_info(true);
            $this->totalSpace = $smaInfo['num_seg'] * $smaInfo['seg_size'];
        }
        return $this->totalSpace;
    }

    /**
     * @return mixed
     */
    public function getAvailableSpace()
    {
        $smaInfo = apc_sma_info(true);
        return $smaInfo['avail_mem'];
    }

    /**
     * @return ApcIterator
     */
    public function getIterator()
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = '';
        $pattern = null;
        if ($namespace !== '') {
            $prefix = $namespace . $options->getNamespaceDelimiter();
            $pattern = '/^' . preg_quote($prefix, '/') . '/';
        }
        $baseIt = new BaseApcIterator('user', $pattern, 0, 1, APC_LIST_ACTIVE);
        return new ApcIterator($this, $baseIt, $prefix);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        return apc_clear_cache('user');
    }

    /**
     * @param $namespace
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function clearByNamespace($namespace)
    {
        $namespace = (string)$namespace;
        if ($namespace === '') {
            throw new \InvalidArgumentException('No namespace given');
        }
        $options = $this->getOptions();
        $prefix = $namespace . $options->getNamespaceDelimiter();
        $pattern = '/^' . preg_quote($prefix, '/') . '/';
        return apc_delete(new BaseApcIterator('user', $pattern, 0, 1, APC_LIST_ACTIVE));
    }

    /**
     * @param $prefix
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function clearByPrefix($prefix)
    {
        $prefix = (string)$prefix;
        if ($prefix === '') {
            throw new \InvalidArgumentException('No prefix given');
        }
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $nsPrefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $pattern = '/^' . preg_quote($nsPrefix . $prefix, '/') . '/';
        return apc_delete(new BaseApcIterator('user', $pattern, 0, 1, APC_LIST_ACTIVE));
    }

    /**
     * @param $normalizedKey
     * @param null $success
     * @param null $casToken
     * @return mixed
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        $result = apc_fetch($internalKey, $success);
        if (!$success) {
            return null;
        }
        $casToken = $result;
        return $result;
    }

    /**
     * @param $normalizedKey
     * @return mixed
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        return apc_exists($prefix . $normalizedKey);
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalGetMetadata(& $normalizedKey)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        if (!apc_exists($internalKey)) {
            $metadata = false;
        } else {
            $format = APC_ITER_ALL ^ APC_ITER_VALUE ^ APC_ITER_TYPE ^ APC_ITER_REFCOUNT;
            $regexp = '/^' . preg_quote($internalKey, '/') . '$/';
            $it = new BaseApcIterator('user', $regexp, $format, 100, APC_LIST_ACTIVE);
            $metadata = $it->current();
        }
        if (!$metadata) {
            return false;
        }
        $this->normalizeMetadata($metadata);
        return $metadata;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        $ttl = $options->getTtl();
        if (!apc_store($internalKey, $value, $ttl)) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new \RuntimeException("apc_store('{$internalKey}', <{$type}>, {$ttl}) failed");
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalAddItem(& $normalizedKey, & $value)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        $ttl = $options->getTtl();
        if (!apc_add($internalKey, $value, $ttl)) {
            if (apc_exists($internalKey)) {
                return false;
            }
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new \RuntimeException("apc_add('{$internalKey}', <{$type}>, {$ttl}) failed");
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        if (!apc_exists($internalKey)) {
            return false;
        }
        $ttl = $options->getTtl();
        if (!apc_store($internalKey, $value, $ttl)) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new \RuntimeException("apc_store('{$internalKey}', <{$type}>, {$ttl}) failed");
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @return mixed
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        return apc_delete($prefix . $normalizedKey);
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     * @throws \RuntimeException
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        $value = (int)$value;
        $newValue = apc_inc($internalKey, $value);
        if ($newValue === false) {
            $ttl = $options->getTtl();
            $newValue = $value;
            if (!apc_add($internalKey, $newValue, $ttl)) {
                throw new \RuntimeException("apc_add('{$internalKey}', {$newValue}, {$ttl}) failed");
            }
        }
        return $newValue;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     * @throws \RuntimeException
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $options = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        $value = (int)$value;
        $newValue = apc_dec($internalKey, $value);
        if ($newValue === false) {
            $ttl = $options->getTtl();
            $newValue = -$value;
            if (!apc_add($internalKey, $newValue, $ttl)) {
                throw new \RuntimeException("apc_add('{$internalKey}', {$newValue}, {$ttl}) failed");
            }
        }
        return $newValue;
    }

    /**
     * @param array $metadata
     */
    protected function normalizeMetadata(array & $metadata)
    {
        $apcMetadata = $metadata;
        $metadata = ['internal_key' => isset($metadata['key']) ? $metadata['key'] : $metadata['info'], 'atime' => isset($metadata['access_time']) ? $metadata['access_time'] : $metadata['atime'], 'ctime' => isset($metadata['creation_time']) ? $metadata['creation_time'] : $metadata['ctime'], 'mtime' => isset($metadata['modified_time']) ? $metadata['modified_time'] : $metadata['mtime'], 'rtime' => isset($metadata['deletion_time']) ? $metadata['deletion_time'] : $metadata['dtime'], 'size' => $metadata['mem_size'], 'hits' => isset($metadata['nhits']) ? $metadata['nhits'] : $metadata['num_hits'], 'ttl' => $metadata['ttl'],];
    }

    /**
     * @param $token
     * @param $normalizedKey
     * @param $value
     * @return bool|mixed
     */
    protected function internalCheckAndSetItem(& $token, & $normalizedKey, & $value)
    {
        if (is_int($token) && is_int($value)) {
            return apc_cas($normalizedKey, $token, $value);
        }
        return parent::internalCheckAndSetItem($token, $normalizedKey, $value);
    }
}
