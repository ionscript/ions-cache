<?php

namespace Ions\Cache\Adapter;

use MongoCollection as MongoResource;
use MongoDate;
use MongoException as MongoResourceException;

/**
 * Class MongoDb
 * @package Ions\Cache\Adapter
 */
class MongoDb extends AbstractAdapter
{
    /**
     * @var bool
     */
    private $initialized = false;
    /**
     * @var
     */
    private $resourceManager;
    /**
     * @var
     */
    private $resourceId;
    /**
     * @var string
     */
    private $namespacePrefix = '';

    /**
     * MongoDb constructor.
     * @param null $options
     * @throws \RuntimeException
     */
    public function __construct($options = null)
    {
        if (!class_exists('Mongo') || !class_exists('MongoClient')) {
            throw new \RuntimeException('MongoDb extension not loaded or Mongo polyfill not included');
        }

        parent::__construct($options);
    }

    /**
     * @return mixed
     */
    private function getMongoDbResource()
    {
        if (!$this->initialized) {
            $options = $this->getOptions();
            $this->resourceManager = $options->getResourceManager();
            $this->resourceId = $options->getResourceId();
            $namespace = $options->getNamespace();
            $this->namespacePrefix = ($namespace === '' ? '' : $namespace . $options->getNamespaceDelimiter());
            $this->initialized = true;
        }

        return $this->resourceManager->getResource($this->resourceId);
    }

    /**
     * @param $options
     * @return $this
     */
    public function setOptions($options)
    {
        return parent::setOptions($options instanceof MongoDbOptions ? $options : new MongoDbOptions($options));
    }

    /**
     * @return mixed
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param $normalizedKey
     * @param null $success
     * @param null $casToken
     * @return mixed
     * @throws \RuntimeException
     */
    protected function internalGetItem(& $normalizedKey, & $success = null, & $casToken = null)
    {
        $result = $this->fetchFromCollection($normalizedKey);
        $success = false;
        if (null === $result) {
            return null;
        }
        if (isset($result['expires'])) {
            if (!$result['expires'] instanceof MongoDate) {
                throw new \RuntimeException(sprintf("The found item _id '%s' for key '%s' is not a valid cache item" . ": the field 'expired' isn't an instance of MongoDate, '%s' found instead", (string)$result['_id'], $this->namespacePrefix . $normalizedKey, is_object($result['expires']) ? get_class($result['expires']) : gettype($result['expires'])));
            }
            if ($result['expires']->sec < time()) {
                $this->internalRemoveItem($normalizedKey);
                return null;
            }
        }
        if (!array_key_exists('value', $result)) {
            throw new \RuntimeException(sprintf("The found item _id '%s' for key '%s' is not a valid cache item: missing the field 'value'", (string)$result['_id'], $this->namespacePrefix . $normalizedKey));
        }
        $success = true;
        return $casToken = $result['value'];
    }

    /**
     * @param $normalizedKey
     * @param $value
     * @return bool
     */
    protected function internalSetItem(& $normalizedKey, & $value)
    {
        $mongo = $this->getMongoDbResource();
        $key = $this->namespacePrefix . $normalizedKey;
        $ttl = $this->getOptions()->getTTl();
        $expires = null;
        $cacheItem = ['key' => $key, 'value' => $value,];
        if ($ttl > 0) {
            $expiresMicro = microtime(true) + $ttl;
            $expiresSecs = (int)$expiresMicro;
            $cacheItem['expires'] = new MongoDate($expiresSecs, $expiresMicro - $expiresSecs);
        }
        try {
            $mongo->remove(['key' => $key]);
            $result = $mongo->insert($cacheItem);
        } catch (MongoResourceException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
        return null !== $result && ((double)1) === $result['ok'];
    }

    /**
     * @param $normalizedKey
     * @return bool
     * @throws \RuntimeException
     */
    protected function internalRemoveItem(& $normalizedKey)
    {
        try {
            $result = $this->getMongoDbResource()->remove(['key' => $this->namespacePrefix . $normalizedKey]);
        } catch (MongoResourceException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
        return false !== $result && ((double)1) === $result['ok'] && $result['n'] > 0;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $result = $this->getMongoDbResource()->drop();
        return ((double)1) === $result['ok'];
    }

    /**
     * @param $normalizedKey
     * @return array|bool
     */
    protected function internalGetMetadata(& $normalizedKey)
    {
        $result = $this->fetchFromCollection($normalizedKey);
        return null !== $result ? ['_id' => $result['_id']] : false;
    }

    /**
     * @param $normalizedKey
     * @return mixed
     * @throws \RuntimeException
     */
    private function fetchFromCollection(& $normalizedKey)
    {
        try {
            return $this->getMongoDbResource()->findOne(['key' => $this->namespacePrefix . $normalizedKey]);
        } catch (MongoResourceException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
