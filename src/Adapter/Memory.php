<?php

namespace Ions\Cache\Adapter;


/**
 * Class Memory
 * @package Ions\Cache\Adapter
 */
class Memory extends AbstractAdapter
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (!$options instanceof MemoryOptions) {
            $options = new MemoryOptions($options);
        }

        return parent::setOptions($options);
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new MemoryOptions());
        }

        return $this->options;
    }

    /**
     * @return mixed
     */
    public function getTotalSpace()
    {
        return $this->getOptions()->getMemoryLimit();
    }

    /**
     * @return float|int
     */
    public function getAvailableSpace()
    {
        $total = $this->getOptions()->getMemoryLimit();

        $avail = $total - (float)memory_get_usage(true);

        return ($avail > 0) ? $avail : 0;
    }

    /**
     * @return KeyListIterator
     */
    public function getIterator()
    {
        $ns = $this->getOptions()->getNamespace();

        $keys = [];

        if (isset($this->data[$ns])) {
            foreach ($this->data[$ns] as $key => & $tmp) {
                if ($this->internalHasItem($key)) {
                    $keys[] = $key;
                }
            }
        }

        return new KeyListIterator($this, $keys);
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $this->data = [];
        return true;
    }

    /**
     * @return bool
     */
    public function clearExpired()
    {
        $ttl = $this->getOptions()->getTtl();

        if ($ttl <= 0) {
            return true;
        }

        $ns = $this->getOptions()->getNamespace();

        if (!isset($this->data[$ns])) {
            return true;
        }

        $data = &$this->data[$ns];

        foreach ($data as $key => & $item) {
            if (microtime(true) >= $data[$key][1] + $ttl) {
                unset($data[$key]);
            }
        }

        return true;
    }

    /**
     * @param $namespace
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function clearByNamespace($namespace)
    {
        $namespace = (string)$namespace;

        if ($namespace === '') {
            throw new \InvalidArgumentException('No namespace given');
        }

        unset($this->data[$namespace]);

        return true;
    }

    /**
     * @param $prefix
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function clearByPrefix($prefix)
    {
        $prefix = (string)$prefix;

        if ($prefix === '') {
            throw new \InvalidArgumentException('No prefix given');
        }

        $ns = $this->getOptions()->getNamespace();

        if (!isset($this->data[$ns])) {
            return true;
        }

        $prefixL = strlen($prefix);
        $data = &$this->data[$ns];

        foreach ($data as $key => & $item) {
            if (substr($key, 0, $prefixL) === $prefix) {
                unset($data[$key]);
            }
        }

        return true;
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
        $ns = $options->getNamespace();
        $success = isset($this->data[$ns][$normalizedKey]);
        if ($success) {
            $data = &$this->data[$ns][$normalizedKey];
            $ttl = $options->getTtl();
            if ($ttl && microtime(true) >= ($data[1] + $ttl)) {
                $success = false;
            }
        }
        if (!$success) {
            return null;
        }
        $casToken = $data[0];
        return $data[0];
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $options = $this->getOptions();
        $ns = $options->getNamespace();
        if (!isset($this->data[$ns][$normalizedKey])) {
            return false;
        }
        $ttl = $options->getTtl();
        if ($ttl && microtime(true) >= ($this->data[$ns][$normalizedKey][1] + $ttl)) {
            return false;
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @return array|bool
     */
    protected function internalGetMetadata(& $normalizedKey)
    {
        if (!$this->internalHasItem($normalizedKey)) {
            return false;
        }
        $ns = $this->getOptions()->getNamespace();
        return ['mtime' => $this->data[$ns][$normalizedKey][1],];
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
        if (!$this->hasAvailableSpace()) {
            $memoryLimit = $options->getMemoryLimit();
            throw new \RuntimeException("Memory usage exceeds limit ({$memoryLimit}).");
        }
        $ns = $options->getNamespace();
        $this->data[$ns][$normalizedKey] = [$value, microtime(true)];
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
        if (!$this->hasAvailableSpace()) {
            $memoryLimit = $options->getMemoryLimit();
            throw new \RuntimeException("Memory usage exceeds limit ({$memoryLimit}).");
        }
        $ns = $options->getNamespace();
        if (isset($this->data[$ns][$normalizedKey])) {
            return false;
        }
        $this->data[$ns][$normalizedKey] = [$value, microtime(true)];
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        $ns = $this->getOptions()->getNamespace();
        if (!isset($this->data[$ns][$normalizedKey])) {
            return false;
        }
        $this->data[$ns][$normalizedKey] = [$value, microtime(true)];
        return true;
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalTouchItem(& $normalizedKey)
    {
        $ns = $this->getOptions()->getNamespace();
        if (!isset($this->data[$ns][$normalizedKey])) {
            return false;
        }
        $this->data[$ns][$normalizedKey][1] = microtime(true);
        return true;
    }

    /**
     * @param $normalizedKey
     * @return bool
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        $ns = $this->getOptions()->getNamespace();
        if (!isset($this->data[$ns][$normalizedKey])) {
            return false;
        }
        unset($this->data[$ns][$normalizedKey]);
        if (!$this->data[$ns]) {
            unset($this->data[$ns]);
        }
        return true;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $ns = $this->getOptions()->getNamespace();
        if (isset($this->data[$ns][$normalizedKey])) {
            $data = &$this->data[$ns][$normalizedKey];
            $data[0] += $value;
            $data[1] = microtime(true);
            $newValue = $data[0];
        } else {
            $newValue = $value;
            $this->data[$ns][$normalizedKey] = [$newValue, microtime(true)];
        }
        return $newValue;
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $ns = $this->getOptions()->getNamespace();
        if (isset($this->data[$ns][$normalizedKey])) {
            $data = &$this->data[$ns][$normalizedKey];
            $data[0] -= $value;
            $data[1] = microtime(true);
            $newValue = $data[0];
        } else {
            $newValue = -$value;
            $this->data[$ns][$normalizedKey] = [$newValue, microtime(true)];
        }
        return $newValue;
    }

    /**
     * @return bool
     */
    protected function hasAvailableSpace()
    {
        $total = $this->getOptions()->getMemoryLimit();
        if ($total <= 0) {
            return true;
        }
        $free = $total - (float)memory_get_usage(true);
        return ($free > 0);
    }
}
