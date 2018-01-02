<?php

namespace Ions\Cache\Adapter;

use Countable;

/**
 * Class KeyListIterator
 * @package Ions\Cache\Adapter
 */
class KeyListIterator implements IteratorInterface, Countable
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var int
     */
    protected $mode = IteratorInterface::CURRENT_AS_KEY;

    /**
     * @var array
     */
    protected $keys;

    /**
     * @var int
     */
    protected $count;

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * KeyListIterator constructor.
     * @param AdapterInterface $adapter
     * @param array $keys
     */
    public function __construct(AdapterInterface $adapter, array $keys)
    {
        $this->adapter = $adapter;
        $this->keys = $keys;
        $this->count = count($keys);
    }

    /**
     * @return AdapterInterface
     */
    public function getStorage()
    {
        return $this->adapter;
    }

    /**
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param $mode
     * @return $this
     */
    public function setMode($mode)
    {
        $this->mode = (int)$mode;
        return $this;
    }

    /**
     * @return $this|mixed
     */
    public function current()
    {
        if ($this->mode == IteratorInterface::CURRENT_AS_SELF) {
            return $this;
        }

        $key = $this->key();

        if ($this->mode == IteratorInterface::CURRENT_AS_METADATA) {
            return $this->adapter->getMetadata($key);
        } elseif ($this->mode == IteratorInterface::CURRENT_AS_VALUE) {
            return $this->adapter->getItem($key);
        }

        return $key;
    }

    /**
     * @return mixed
     */
    public function key()
    {
        return $this->keys[$this->position];
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->position < $this->count;
    }

    /**
     * @return void
     */
    public function next()
    {
        $this->position++;
    }

    /**
     * @return void
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->count;
    }
}
