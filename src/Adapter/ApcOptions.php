<?php

namespace Ions\Cache\Adapter;

/**
 * Class ApcOptions
 * @package Ions\Cache\Adapter
 */
class ApcOptions extends AdapterOptions
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
