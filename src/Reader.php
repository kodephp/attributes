<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Default attribute reader implementation.
 * 
 * Reads attributes from various targets using PHP's Reflection API
 * and provides a consistent interface for accessing them.
 * 
 * @package Kode\Attributes
 */
final class Reader implements ReaderInterface
{
    private CacheInterface $cache;

    /**
     * Create a new Reader instance.
     * 
     * @param CacheInterface|null $cache Optional cache implementation
     */
    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? new ArrayCache();
    }

    /**
     * Get attributes for a target (class, method, property, etc.).
     * 
     * @param object|string $target The target to get attributes for
     * @return MetaList
     */
    public function getAttributes(object|string $target): MetaList
    {
        if (is_string($target)) {
            return $this->getClassAttrs($target);
        }

        if (is_object($target)) {
            return $this->getObjectAttrs($target);
        }

        throw new \InvalidArgumentException('Target must be a class name or object');
    }

    /**
     * Get attributes for an object.
     * 
     * @param object $object The object to get attributes for
     * @return MetaList
     */
    public function getObjectAttrs(object $object): MetaList
    {
        $class = get_class($object);
        return $this->getClassAttrs($class);
    }

    /**
     * {@inheritDoc}
     */
    public function getClassAttrs(string $class): MetaList
    {
        $key = "class:{$class}";
        
        return $this->cache->get($key, function() use ($class) {
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getMethodAttrs(string $class, string $method): MetaList
    {
        $key = "method:{$class}:{$method}";
        
        return $this->cache->get($key, function() use ($class, $method) {
            $reflection = new \ReflectionMethod($class, $method);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyAttrs(string $class, string $property): MetaList
    {
        $key = "property:{$class}:{$property}";
        
        return $this->cache->get($key, function() use ($class, $property) {
            $reflection = new \ReflectionProperty($class, $property);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctionAttrs(string $function): MetaList
    {
        $key = "function:{$function}";
        
        return $this->cache->get($key, function() use ($function) {
            $reflection = new \ReflectionFunction($function);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getParameterAttrs(\ReflectionParameter $param): MetaList
    {
        $key = "parameter:" . $param->getDeclaringFunction()->getName() . ":" . $param->getName();
        
        return $this->cache->get($key, function() use ($param) {
            $attributes = $param->getAttributes();
            return $this->createMetaList($attributes);
        });
    }

    /**
     * Create a MetaList from an array of ReflectionAttribute objects.
     * 
     * @param \ReflectionAttribute[] $attributes The reflection attributes
     * @return MetaList
     */
    private function createMetaList(array $attributes): MetaList
    {
        $metaList = [];
        
        foreach ($attributes as $attribute) {
            try {
                $metaList[] = new Meta($attribute);
            } catch (\Throwable $e) {
                // Skip attributes that can't be processed
                continue;
            }
        }
        
        return new MetaList($metaList);
    }
}