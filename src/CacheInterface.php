<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Interface for attribute cache implementations.
 * 
 * Defines the contract for caching attribute metadata to improve performance.
 * 
 * @package Kode\Attributes
 */
interface CacheInterface
{
    /**
     * Get a value from the cache, or store a new value if it doesn't exist.
     * 
     * @param string $key The cache key
     * @param callable $loader A callable that returns the value to cache
     * @return mixed
     */
    public function get(string $key, callable $loader): mixed;

    /**
     * Clear all cached values.
     * 
     * @return void
     */
    public function clear(): void;
}