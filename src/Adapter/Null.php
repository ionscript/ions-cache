<?php

namespace Ions\Cache\Adapter;

/**
 * Class Null
 * @package Ions\Cache\Adapter
 */
class Null implements AdapterInterface
{
    /**
     * @var
     */
    protected $options;

    /**
     * Null constructor.
     * @param null $options
     */
    public function __construct($options = null)
    {
        if ($options) {
            $this->setOptions($options);
        }
    }

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        if ($this->options !== $options) {
            if (!$options instanceof AdapterOptions) {
                $options = new AdapterOptions($options);
            }
            if ($this->options) {
                $this->options->setAdapter(null);
            }
            $options->setAdapter($this);
            $this->options = $options;
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        if (!$this->options) {
            $this->setOptions(new AdapterOptions());
        }
        return $this->options;
    }

    /**
     * @param $key
     * @param null $success
     * @param null $casToken
     * @return void
     */
    public function getItem($key, & $success = null, & $casToken = null)
    {
        $success = false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function hasItem($key)
    {
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function getMetadata($key)
    {
        return false;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function setItem($key, $value)
    {
        return false;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function addItem($key, $value)
    {
        return false;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function replaceItem($key, $value)
    {
        return false;
    }

    /**
     * @param $token
     * @param $key
     * @param $value
     * @return bool
     */
    public function checkAndSetItem($token, $key, $value)
    {
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function touchItem($key)
    {
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function removeItem($key)
    {
        return false;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function incrementItem($key, $value)
    {
        return false;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function decrementItem($key, $value)
    {
        return false;
    }

    /**
     * @return int
     */
    public function getAvailableSpace()
    {
        return 0;
    }

    /**
     * @param $namespace
     * @return bool
     */
    public function clearByNamespace($namespace)
    {
        return false;
    }

    /**
     * @param $prefix
     * @return bool
     */
    public function clearByPrefix($prefix)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function clearExpired()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        return false;
    }

    /**
     * @return KeyListIterator
     */
    public function getIterator()
    {
        return new KeyListIterator($this, []);
    }

    /**
     * @return bool
     */
    public function optimize()
    {
        return false;
    }

    /**
     * @return int
     */
    public function getTotalSpace()
    {
        return 0;
    }
}
