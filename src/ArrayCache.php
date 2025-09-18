<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * In-memory array cache implementation.
 * 
 * Provides a simple in-memory cache for attribute metadata.
 * 
 * @package Kode\Attributes
 */
final class ArrayCache implements CacheInterface
{
    /**
     * The cache storage.
     * 
     * @var array<string, mixed>
     */
    private array $cache = [];
    
    /**
     * Cache statistics.
     * 
     * @var array{hits: int, misses: int}
     */
    private array $stats = ['hits' => 0, 'misses' => 0];

    /**
     * {@inheritDoc}
     */
    public function get(string $key, callable $loader): mixed
    {
        if (!isset($this->cache[$key])) {
            $this->stats['misses']++;
            $this->cache[$key] = $loader();
        } else {
            $this->stats['hits']++;
        }
        
        return $this->cache[$key];
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->stats = ['hits' => 0, 'misses' => 0];
    }
    
    /**
     * Get cache statistics.
     * 
     * @return array{hits: int, misses: int, hitRate: float}
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) : 0;
        
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'hitRate' => $hitRate
        ];
    }
    
    /**
     * Get the number of cached items.
     * 
     * @return int
     */
    public function getSize(): int
    {
        return count($this->cache);
    }
}