<?php

namespace Ions\Cache\Adapter;

/**
 * Interface AdapterInterface
 * @package Ions\Cache\Adapter
 */
interface AdapterInterface
{
    /**
     * @param $options
     * @return mixed
     */
    public function setOptions($options);

    /**
     * @return mixed
     */
    public function getOptions();

    /**
     * @param $key
     * @param null $success
     * @param null $casToken
     * @return mixed
     */
    public function getItem($key, & $success = null, & $casToken = null);

    /**
     * @param $key
     * @return mixed
     */
    public function hasItem($key);

    /**
     * @param $key
     * @return mixed
     */
    public function getMetadata($key);

    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public function setItem($key, $value);

    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public function addItem($key, $value);

    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public function replaceItem($key, $value);

    /**
     * @param $token
     * @param $key
     * @param $value
     * @return mixed
     */
    public function checkAndSetItem($token, $key, $value);

    /**
     * @param $key
     * @return mixed
     */
    public function touchItem($key);

    /**
     * @param $key
     * @return mixed
     */
    public function removeItem($key);

    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public function incrementItem($key, $value);

    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public function decrementItem($key, $value);
}
