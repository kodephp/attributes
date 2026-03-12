<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * 内存数组缓存实现类。
 * 
 * 提供简单的进程内内存缓存，适用于单次请求生命周期内的缓存。
 * 包含缓存统计功能，便于性能分析。
 * 
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class ArrayCache implements CacheInterface
{
    /**
     * 缓存存储。
     * 
     * @var array<string, mixed>
     */
    private array $cache = [];
    
    /**
     * 缓存统计信息。
     * 
     * @var array{hits: int, misses: int}
     */
    private array $stats = ['hits' => 0, 'misses' => 0];

    /**
     * {@inheritDoc}
     */
    public function get(string $key, callable $loader): mixed
    {
        if (!$this->has($key)) {
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
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): void
    {
        unset($this->cache[$key]);
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
     * 获取缓存统计信息。
     * 
     * @return array{hits: int, misses: int, hitRate: float, size: int} 统计信息
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) : 0.0;
        
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'hitRate' => $hitRate,
            'size' => $this->getSize(),
        ];
    }
    
    /**
     * 获取缓存项数量。
     * 
     * @return int 缓存项数量
     */
    public function getSize(): int
    {
        return count($this->cache);
    }

    /**
     * 获取所有缓存键。
     * 
     * @return array<string> 缓存键数组
     */
    public function getKeys(): array
    {
        return array_keys($this->cache);
    }

    /**
     * 获取缓存命中率百分比。
     * 
     * @return float 命中率（0-100）
     */
    public function getHitRatePercent(): float
    {
        $stats = $this->getStats();
        return $stats['hitRate'] * 100;
    }
}
