<?php

namespace Ions\Cache\Adapter;

/**
 * Class ApcuOptions
 * @package Ions\Cache\Adapter
 */
class ApcuOptions extends AdapterOptions
{
    /**
     * @var string
     */
    protected $namespaceDelimiter = ':';

    /**
     * @param $namespaceDelimiter
     * @return $this
     */
    public function setNamespaceDelimiter($namespaceDelimiter)
    {
        $namespaceDelimiter = (string)$namespaceDelimiter;
        $this->namespaceDelimiter = $namespaceDelimiter;
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespaceDelimiter()
    {
        return $this->namespaceDelimiter;
    }
}
