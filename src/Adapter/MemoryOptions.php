<?php

namespace Ions\Cache\Adapter;

/**
 * Class MemoryOptions
 * @package Ions\Cache\Adapter
 */
class MemoryOptions extends AdapterOptions
{
    /**
     * @var
     */
    protected $memoryLimit;

    /**
     * @param $memoryLimit
     * @return $this
     */
    public function setMemoryLimit($memoryLimit)
    {
        $memoryLimit = $this->normalizeMemoryLimit($memoryLimit);
        if ($this->memoryLimit != $memoryLimit) {
            $this->memoryLimit = $memoryLimit;
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getMemoryLimit()
    {
        if ($this->memoryLimit === null) {
            $memoryLimit = $this->normalizeMemoryLimit(ini_get('memory_limit'));
            if ($memoryLimit >= 0) {
                $this->memoryLimit = (int)($memoryLimit / 2);
            } else {
                $this->memoryLimit = 0;
            }
        }
        return $this->memoryLimit;
    }

    /**
     * @param $value
     * @return int
     * @throws \InvalidArgumentException
     */
    protected function normalizeMemoryLimit($value)
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        if (!preg_match('/(\-?\d+)\s*(\w*)/', ini_get('memory_limit'), $matches)) {
            throw new \InvalidArgumentException("Invalid  memory limit '{$value}'");
        }
        $value = (int)$matches[1];
        if ($value <= 0) {
            return 0;
        }
        switch (strtoupper($matches[2])) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }
        return $value;
    }
}

