<?php

/*
 * This file is part of the kode/attributes package.
 *
 * (c) kode (KodePHP) <382601296@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * 内存数组缓存实现类。
 *
 * 提供进程内内存缓存，适用于单次请求（或常驻进程）生命周期内的反射缓存。
 * 内置 LRU 淘汰策略，避免在 Swoole / Workerman / RoadRunner 等常驻场景下
 * 因扫描大量类（尤其是匿名类）而导致内存无限增长。
 *
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final class ArrayCache implements CacheInterface
{
    /**
     * 默认容量上限。
     */
    public const int DEFAULT_CAPACITY = 4096;

    /**
     * 缓存存储。
     *
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * 缓存统计信息。
     *
     * @var array{hits: int, misses: int, evictions: int}
     */
    private array $stats = ['hits' => 0, 'misses' => 0, 'evictions' => 0];

    /**
     * 创建缓存实例。
     *
     * @param int $capacity 容量上限，<= 0 表示不限制
     */
    public function __construct(private readonly int $capacity = self::DEFAULT_CAPACITY)
    {
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function get(string $key, callable $loader): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            $this->stats['hits']++;
            $value = $this->cache[$key];

            // LRU：命中后移动到队尾。
            unset($this->cache[$key]);
            $this->cache[$key] = $value;

            return $value;
        }

        $this->stats['misses']++;
        $value = $loader();
        $this->cache[$key] = $value;
        $this->evictIfNeeded();

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function set(string $key, mixed $value): void
    {
        unset($this->cache[$key]);
        $this->cache[$key] = $value;
        $this->evictIfNeeded();
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function delete(string $key): void
    {
        unset($this->cache[$key]);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function clear(): void
    {
        $this->cache = [];
        $this->stats = ['hits' => 0, 'misses' => 0, 'evictions' => 0];
    }

    /**
     * 按前缀批量删除缓存。
     *
     * @param string $prefix 键前缀
     * @return int 删除数量
     */
    public function deleteByPrefix(string $prefix): int
    {
        $deleted = 0;

        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->cache[$key]);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * 获取缓存统计信息。
     *
     * @return array{hits: int, misses: int, evictions: int, hitRate: float, size: int, capacity: int} 统计信息
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];

        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'evictions' => $this->stats['evictions'],
            'hitRate' => $total > 0 ? ($this->stats['hits'] / $total) : 0.0,
            'size' => $this->getSize(),
            'capacity' => $this->capacity,
        ];
    }

    /**
     * 获取缓存项数量。
     */
    public function getSize(): int
    {
        return count($this->cache);
    }

    /**
     * 获取容量上限。
     */
    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /**
     * 获取所有缓存键。
     *
     * @return array<int, string> 缓存键数组
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
        return $this->getStats()['hitRate'] * 100;
    }

    /**
     * 超出容量时按 LRU 淘汰最久未使用的项。
     */
    private function evictIfNeeded(): void
    {
        if ($this->capacity <= 0) {
            return;
        }

        while (count($this->cache) > $this->capacity) {
            $oldest = array_key_first($this->cache);

            if ($oldest === null) {
                return;
            }

            unset($this->cache[$oldest]);
            $this->stats['evictions']++;
        }
    }
}
