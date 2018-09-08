<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Adds a PSR-6 cache layer on top of an extractor.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class PropertyInfoCacheExtractor implements PropertyInfoExtractorInterface, PropertyInitializableExtractorInterface
{
    private $propertyInfoExtractor;
    private $cacheItemPool;
    private $arrayCache = array();

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor, CacheItemPoolInterface $cacheItemPool)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->cacheItemPool = $cacheItemPool;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = array())
    {
        return $this->extract('isReadable', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = array())
    {
        return $this->extract('isWritable', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = array())
    {
        return $this->extract('getShortDescription', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = array())
    {
        return $this->extract('getLongDescription', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        return $this->extract('getProperties', array($class, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        return $this->extract('getTypes', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function isInitializable(string $class, string $property, array $context = array()): ?bool
    {
        return $this->extract('isInitializable', array($class, $property, $context));
    }

    /**
     * Retrieves the cached data if applicable or delegates to the decorated extractor.
     *
     * @return mixed
     */
    private function extract(string $method, array $arguments)
    {
        try {
            $serializedArguments = serialize($arguments);
        } catch (\Exception $exception) {
            // If arguments are not serializable, skip the cache
            return \call_user_func_array(array($this->propertyInfoExtractor, $method), $arguments);
        }

        // Calling rawurlencode escapes special characters not allowed in PSR-6's keys
        $key = rawurlencode($method.'.'.$serializedArguments);

        if (array_key_exists($key, $this->arrayCache)) {
            return $this->arrayCache[$key];
        }

        $item = $this->cacheItemPool->getItem($key);

        if ($item->isHit()) {
            return $this->arrayCache[$key] = $item->get();
        }

        $value = \call_user_func_array(array($this->propertyInfoExtractor, $method), $arguments);
        $item->set($value);
        $this->cacheItemPool->save($item);

        return $this->arrayCache[$key] = $value;
    }
}