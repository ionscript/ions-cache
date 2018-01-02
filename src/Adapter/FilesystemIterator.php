<?php

namespace Ions\Cache\Adapter;

use GlobIterator;

/**
 * Class FilesystemIterator
 * @package Ions\Cache\Adapter
 */
class FilesystemIterator implements IteratorInterface
{
    /**
     * @var Filesystem
     */
    protected $adapter;

    /**
     * @var int
     */
    protected $mode = IteratorInterface::CURRENT_AS_KEY;

    /**
     * @var GlobIterator
     */
    protected $globIterator;

    /**
     * @var
     */
    protected $prefix;

    /**
     * @var int
     */
    protected $prefixLength;

    /**
     * FilesystemIterator constructor.
     * @param Filesystem $adapter
     * @param $path
     * @param $prefix
     */
    public function __construct(Filesystem $adapter, $path, $prefix)
    {
        $this->adapter = $adapter;
        $this->globIterator = new GlobIterator($path, GlobIterator::KEY_AS_FILENAME);
        $this->prefix = $prefix;
        $this->prefixLength = strlen($prefix);
    }

    /**
     * @return Filesystem
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
        $filename = $this->globIterator->key();

        return substr($filename, $this->prefixLength, -4);
    }

    /**
     * @return void
     */
    public function next()
    {
        $this->globIterator->next();
    }

    /**
     * @return bool
     */
    public function valid()
    {
        try {
            return $this->globIterator->valid();
        } catch (\LogicException $e) {
            return false;
        }
    }

    /**
     * @return bool|void
     */
    public function rewind()
    {
        try {
            return $this->globIterator->rewind();
        } catch (\LogicException $e) {
            return false;
        }
    }
}
