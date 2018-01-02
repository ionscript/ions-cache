<?php

namespace Ions\Cache\Adapter;

use Iterator;

/**
 * Interface IteratorInterface
 * @package Ions\Cache\Adapter
 */
interface IteratorInterface extends Iterator
{
    const CURRENT_AS_SELF = 0;
    const CURRENT_AS_KEY = 1;
    const CURRENT_AS_VALUE = 2;
    const CURRENT_AS_METADATA = 3;

    /**
     * @return mixed
     */
    public function getStorage();

    /**
     * @return mixed
     */
    public function getMode();

    /**
     * @param $mode
     * @return mixed
     */
    public function setMode($mode);
}
