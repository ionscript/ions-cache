<?php

namespace Ions\Cache\Adapter;

use APCuIterator as BaseApcuIterator;

/**
 * Class Apcu
 * @package Ions\Cache\Adapter
 */
class Apcu extends AbstractAdapter
{
    /**
     * @var
     */
    protected $totalSpace;

    /**
     * Apcu constructor.
     * @param null $options
     * @throws \RuntimeException
     */
    public function __construct($options = null)
    {
        if (!ini_get('apc.enabled') || (PHP_SAPI === 'cli' && !ini_get('apc.enable_cli'))) {
            throw new \RuntimeException("ext/apcu is disabled - see 'apc.enabled' and 'apc.enable_cli'");
        }

        parent::__construct($options);
    }

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (!$options instanceof ApcuOptions) {
            $options = new ApcuOptions($options);
        }
        return parent::setOptions($options);
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new ApcuOptions());
        }
        return $this->options;
    }

    /**
     * @return mixed
     */
    public function getTotalSpace()
    {
        if ($this->totalSpace === null) {
            $smaInfo = apcu_sma_info(true);
            $this->totalSpace = $smaInfo['num_seg'] * $smaInfo['seg_size'];
        }
        return $this->totalSpace;
    }

    /**
     * @return mixed
     */
    public function getAvailableSpace()
    {
        $smaInfo = apcu_sma_info(true);
        return $smaInfo['avail_mem'];
    }

    /**
     * @return ApcuIterator
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
        $baseIt = new BaseApcuIterator($pattern, 0, 1, APC_LIST_ACTIVE);
        return new ApcuIterator($this, $baseIt, $prefix);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        return apcu_clear_cache();
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
        return apcu_delete(new BaseApcuIterator($pattern, 0, 1, APC_LIST_ACTIVE));
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
        return apcu_delete(new BaseApcuIterator($pattern, 0, 1, APC_LIST_ACTIVE));
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
        $result = apcu_fetch($internalKey, $success);
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
        return apcu_exists($prefix . $normalizedKey);
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
        $format = APC_ITER_ALL ^ APC_ITER_VALUE ^ APC_ITER_TYPE ^ APC_ITER_REFCOUNT;
        $regexp = '/^' . preg_quote($internalKey, '/') . '$/';
        $it = new BaseApcuIterator($regexp, $format, 100, APC_LIST_ACTIVE);
        $metadata = $it->current();
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
        if (!apcu_store($internalKey, $value, $ttl)) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new \RuntimeException("apcu_store('{$internalKey}', <{$type}>, {$ttl}) failed");
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
        if (!apcu_add($internalKey, $value, $ttl)) {
            if (apcu_exists($internalKey)) {
                return false;
            }
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new \RuntimeException("apcu_add('{$internalKey}', <{$type}>, {$ttl}) failed");
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
        $ttl = $options->getTtl();
        $namespace = $options->getNamespace();
        $prefix = ($namespace === '') ? '' : $namespace . $options->getNamespaceDelimiter();
        $internalKey = $prefix . $normalizedKey;
        if (!apcu_exists($internalKey)) {
            return false;
        }
        if (!apcu_store($internalKey, $value, $ttl)) {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new \RuntimeException("apcu_store('{$internalKey}', <{$type}>, {$ttl}) failed");
        }
        return true;
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
            return apcu_cas($normalizedKey, $token, $value);
        }
        return parent::internalCheckAndSetItem($token, $normalizedKey, $value);
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
        return apcu_delete($prefix . $normalizedKey);
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
        $newValue = apcu_inc($internalKey, $value);
        if ($newValue === false) {
            $ttl = $options->getTtl();
            $newValue = $value;
            if (!apcu_add($internalKey, $newValue, $ttl)) {
                throw new \RuntimeException("apcu_add('{$internalKey}', {$newValue}, {$ttl}) failed");
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
        $newValue = apcu_dec($internalKey, $value);
        if ($newValue === false) {
            $ttl = $options->getTtl();
            $newValue = -$value;
            if (!apcu_add($internalKey, $newValue, $ttl)) {
                throw new \RuntimeException("apcu_add('{$internalKey}', {$newValue}, {$ttl}) failed");
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
        $metadata = ['internal_key' => $metadata['key'], 'atime' => $metadata['access_time'], 'ctime' => $metadata['creation_time'], 'mtime' => $metadata['mtime'], 'rtime' => $metadata['deletion_time'], 'size' => $metadata['mem_size'], 'hits' => $metadata['num_hits'], 'ttl' => $metadata['ttl'],];
    }
}
