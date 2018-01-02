<?php

namespace Ions\Cache\Adapter;

use APCuIterator as BaseApcuIterator;

/**
 * Class ApcuIterator
 * @package Ions\Cache\Adapter
 */
class ApcuIterator implements IteratorInterface
{
    /**
     * @var Apcu
     */
    protected $adapter;

    /**
     * @var int
     */
    protected $mode = IteratorInterface::CURRENT_AS_KEY;

    /**
     * @var BaseApcuIterator
     */
    protected $baseIterator;

    /**
     * @var int
     */
    protected $prefixLength;

    /**
     * ApcuIterator constructor.
     * @param Apcu $adapter
     * @param BaseApcuIterator $baseIterator
     * @param $prefix
     */
    public function __construct(Apcu $adapter, BaseApcuIterator $baseIterator, $prefix)
    {
        $this->adapter = $adapter;
        $this->baseIterator = $baseIterator;
        $this->prefixLength = strlen($prefix);
    }

    /**
     * @return Apcu
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
     * @return $this|array|bool|\Exception|mixed|null|string
     */
    public function current()
    {
        if ($this->mode == IteratorInterface::CURRENT_AS_SELF) {
            return $this;
        }
        $key = $this->key();
        if ($this->mode == IteratorInterface::CURRENT_AS_VALUE) {
            return $this->adapter->getItem($key);
        } elseif ($this->mode == IteratorInterface::CURRENT_AS_METADATA) {
            return $this->adapter->getMetadata($key);
        }
        return $key;
    }

    /**
     * @return bool|string
     */
    public function key()
    {
        $key = $this->baseIterator->key();
        return substr($key, $this->prefixLength);
    }

    /**
     * @return void
     */
    public function next()
    {
        $this->baseIterator->next();
    }

    /**
     * @return mixed
     */
    public function valid()
    {
        return $this->baseIterator->valid();
    }

    /**
     * @return mixed
     */
    public function rewind()
    {
        return $this->baseIterator->rewind();
    }
}
