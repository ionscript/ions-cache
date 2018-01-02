<?php

namespace Ions\Cache\Adapter;

use ArrayObject;

/**
 * Class AbstractAdapter
 * @package Ions\Cache\Adapter
 */
abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * @var
     */
    protected $options;

    /**
     * @var
     */
    protected $events;

    /**
     * AbstractAdapter constructor.
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
     * @param $flag
     * @return $this
     */
    public function setCaching($flag)
    {
        $flag = (bool)$flag;

        $options = $this->getOptions();

        $options->setWritable($flag);

        $options->setReadable($flag);

        return $this;
    }

    /**
     * @return bool
     */
    public function getCaching()
    {
        $options = $this->getOptions();

        return ($options->getWritable() && $options->getReadable());
    }

    /**
     * @param $key
     * @param null $success
     * @param null $casToken
     * @return \Exception|null
     * @throws \InvalidArgumentException
     */
    public function getItem($key, & $success = null, & $casToken = null)
    {
        if (!$this->getOptions()->getReadable()) {
            $success = false;
            return null;
        }

        $this->normalizeKey($key);

        $argn = func_num_args();

        $args = ['key' => & $key,];

        if ($argn > 1) {
            $args['success'] = &$success;
        }

        if ($argn > 2) {
            $args['casToken'] = &$casToken;
        }

        $args = new ArrayObject($args);

        try {

            if ($args->offsetExists('success') && $args->offsetExists('casToken')) {
                $result = $this->internalGetItem($args['key'], $args['success'], $args['casToken']);
            } elseif ($args->offsetExists('success')) {
                $result = $this->internalGetItem($args['key'], $args['success']);
            } else {
                $result = $this->internalGetItem($args['key']);
            }

            return $result;
        } catch (\Exception $e) {
            $result = null;
            $success = false;
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @param null $success
     * @param null $casToken
     * @return mixed
     */
    abstract protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null);

    public function hasItem($key)
    {
        if (!$this->getOptions()->getReadable()) {
            return false;
        }

        $this->normalizeKey($key);

        $args = new ArrayObject(['key' => & $key,]);

        try {
            return $this->internalHasItem($args['key']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @return null
     */
    protected function internalHasItem(& $normalizedKey)
    {
        $success = null;
        $this->internalGetItem($normalizedKey, $success);
        return $success;
    }

    /**
     * @param $key
     * @return array|bool|\Exception
     * @throws \InvalidArgumentException
     */
    public function getMetadata($key)
    {
        if (!$this->getOptions()->getReadable()) {
            return false;
        }

        $this->normalizeKey($key);

        $args = new ArrayObject(['key' => & $key,]);

        try {
            return $this->internalGetMetadata($args['key']);
        } catch (\Exception $e) {
            return $e;
        }
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

        return [];
    }

    /**
     * @param $key
     * @param $value
     * @return bool|\Exception
     * @throws \InvalidArgumentException
     */
    public function setItem($key, $value)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);

        $args = new ArrayObject([
            'key' => & $key,
            'value' => & $value,
        ]);

        try {
            return $this->internalSetItem($args['key'], $args['value']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return mixed
     */
    abstract protected function internalSetItem(& $normalizedKey, & $value);

    /**
     * @param $key
     * @param $value
     * @return bool|\Exception|mixed
     * @throws \InvalidArgumentException
     */
    public function addItem($key, $value)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);
        $args = new ArrayObject(['key' => & $key, 'value' => & $value,]);

        try {
            return $this->internalAddItem($args['key'], $args['value']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool|mixed
     */
    protected function internalAddItem(& $normalizedKey, & $value)
    {
        if ($this->internalHasItem($normalizedKey)) {
            return false;
        }
        return $this->internalSetItem($normalizedKey, $value);
    }

    /**
     * @param $key
     * @param $value
     * @return bool|\Exception|mixed
     * @throws \InvalidArgumentException
     */
    public function replaceItem($key, $value)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);
        $args = new ArrayObject(['key' => & $key, 'value' => & $value,]);

        try {
            return $this->internalReplaceItem($args['key'], $args['value']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool|mixed
     */
    protected function internalReplaceItem(& $normalizedKey, & $value)
    {
        if (!$this->internalhasItem($normalizedKey)) {
            return false;
        }

        return $this->internalSetItem($normalizedKey, $value);
    }

    /**
     * @param $token
     * @param $key
     * @param $value
     * @return bool|\Exception|mixed
     * @throws \InvalidArgumentException
     */
    public function checkAndSetItem($token, $key, $value)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);
        $args = new ArrayObject(['token' => & $token, 'key' => & $key, 'value' => & $value,]);

        try {
            return $this->internalCheckAndSetItem($args['token'], $args['key'], $args['value']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $token
     * @param $normalizedKey
     * @param $value
     * @return bool|mixed
     */
    protected function internalCheckAndSetItem(& $token, & $normalizedKey, & $value)
    {
        $oldValue = $this->internalGetItem($normalizedKey);

        if ($oldValue !== $token) {
            return false;
        }

        return $this->internalSetItem($normalizedKey, $value);
    }

    /**
     * @param $key
     * @return bool|\Exception|mixed
     * @throws \InvalidArgumentException
     */
    public function touchItem($key)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);
        $args = new ArrayObject(['key' => & $key,]);

        try {
            return $this->internalTouchItem($args['key']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @return bool|mixed
     */
    protected function internalTouchItem(& $normalizedKey)
    {
        $success = null;

        $value = $this->internalGetItem($normalizedKey, $success);
        if (!$success) {
            return false;
        }

        return $this->internalReplaceItem($normalizedKey, $value);
    }

    /**
     * @param $key
     * @return bool|\Exception
     * @throws \InvalidArgumentException
     */
    public function removeItem($key)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);
        $args = new ArrayObject(['key' => & $key,]);

        try {
            return $this->internalRemoveItem($args['key']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @return mixed
     */
    abstract protected function internalRemoveItem(& $normalizedKey);

    /**
     * @param $key
     * @param $value
     * @return bool|\Exception|int
     * @throws \InvalidArgumentException
     */
    public function incrementItem($key, $value)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);
        $args = new ArrayObject(['key' => & $key, 'value' => & $value,]);

        try {
            return $this->internalIncrementItem($args['key'], $args['value']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     */
    protected function internalIncrementItem(& $normalizedKey, & $value)
    {
        $success = null;
        $value = (int)$value;
        $get = (int)$this->internalGetItem($normalizedKey, $success);
        $newValue = $get + $value;

        if ($success) {
            $this->internalReplaceItem($normalizedKey, $newValue);
        } else {
            $this->internalAddItem($normalizedKey, $newValue);
        }

        return $newValue;
    }

    /**
     * @param $key
     * @param $value
     * @return bool|\Exception|int
     * @throws \InvalidArgumentException
     */
    public function decrementItem($key, $value)
    {
        if (!$this->getOptions()->getWritable()) {
            return false;
        }

        $this->normalizeKey($key);
        $args = new ArrayObject(['key' => & $key, 'value' => & $value,]);

        try {
            return $this->internalDecrementItem($args['key'], $args['value']);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return int
     */
    protected function internalDecrementItem(& $normalizedKey, & $value)
    {
        $success = null;
        $value = (int)$value;
        $get = (int)$this->internalGetItem($normalizedKey, $success);
        $newValue = $get - $value;

        if ($success) {
            $this->internalReplaceItem($normalizedKey, $newValue);
        } else {
            $this->internalAddItem($normalizedKey, $newValue);
        }

        return $newValue;
    }

    /**
     * @param $key
     * @throws \InvalidArgumentException
     */
    protected function normalizeKey(& $key)
    {
        $key = (string)$key;

        if ($key === '') {
            throw new \InvalidArgumentException("An empty key isn't allowed");
        }
    }

    /**
     * @param array $keys
     * @throws \InvalidArgumentException
     */
    protected function normalizeKeys(array & $keys)
    {
        if (!$keys) {
            throw new \InvalidArgumentException("An empty list of keys isn't allowed");
        }

        array_walk($keys, [$this, 'normalizeKey']);
        $keys = array_values(array_unique($keys));
    }

    /**
     * @param array $keyValuePairs
     * @throws \InvalidArgumentException
     */
    protected function normalizeKeyValuePairs(array & $keyValuePairs)
    {
        $normalizedKeyValuePairs = [];
        foreach ($keyValuePairs as $key => $value) {
            $this->normalizeKey($key);
            $normalizedKeyValuePairs[$key] = $value;
        }
        $keyValuePairs = $normalizedKeyValuePairs;
    }
}
