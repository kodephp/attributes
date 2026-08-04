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

namespace Kode\Attributes\Cache;

use Kode\Attributes\CacheInterface;
use Kode\Attributes\MetaList;
use Redis;
use Throwable;

/**
 * 基于 Redis 的共享属性缓存适配器。
 *
 * 面向「多进程」「多线程 worker」「协程 / Fibers」「分布式」场景，使反射元数据
 * 在 **同一进程之外** 依然可被复用：
 *
 * - **多进程**（Swoole / Workerman 多 worker、FPM 多进程）：进程间共享反射结果，避免重复反射；
 * - **分布式**（多台服务器）：通过 Redis 集群共享缓存，降低跨节点反射开销；
 * - **常驻进程 + 热重启**：worker 退出后缓存仍保留于 Redis，重启即命中。
 *
 * 说明：
 * - 缓存值必须可序列化。本包的 `Meta` / `MetaList` 已支持原生序列化
 *   （见 {@see \Kode\Attributes\Meta::__serialize}），其中「已实例化的属性对象」会被一并保留，
 *   因此跨进程 / 跨节点取回的 {@see MetaList} 仍可直接返回实例。
 * - 若某个属性实例本身不可序列化（极少见），会自动降级为「纯数据快照」存储
 *   （丢失实例、仅保留声明），保证缓存写入不抛异常。
 * - 该适配器依赖 `ext-redis`；无 Redis 环境仍可使用默认的 {@see ArrayCache}（进程内）。
 *
 * @package Kode\Attributes\Cache
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final class RedisCache implements CacheInterface
{
    /**
     * 默认键前缀（用于隔离不同应用 / 版本）。
     */
    public const string DEFAULT_PREFIX = 'kode:attr:';

    /**
     * 默认过期时间（秒）。
     */
    public const int DEFAULT_TTL = 3600;

    /**
     * @param Redis $redis 已连接的 Redis 实例
     * @param string $prefix 键前缀（建议包含应用名 / 版本，避免与其它键冲突）
     * @param int $ttl 缓存过期时间（秒），<= 0 表示不过期
     */
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = self::DEFAULT_PREFIX,
        private readonly int $ttl = self::DEFAULT_TTL
    ) {
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function get(string $key, callable $loader): mixed
    {
        $rk = $this->key($key);
        $raw = $this->redis->get($rk);

        if ($raw !== false && $raw !== null) {
            return $this->unpack($raw);
        }

        $value = $loader();
        $this->store($rk, $this->pack($value));

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function has(string $key): bool
    {
        return $this->redis->exists($this->key($key)) > 0;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $this->store($this->key($key), $this->pack($value));
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function delete(string $key): void
    {
        $this->redis->del($this->key($key));
    }

    /**
     * {@inheritDoc}
     *
     * 通过 `SCAN` 按前缀批量删除，避免 `KEYS` 阻塞 Redis。
     */
    #[\Override]
    public function clear(): void
    {
        $it = null;

        do {
            $keys = $this->redis->scan($it, $this->prefix . '*', 200);

            if ($keys === false) {
                break;
            }

            if ($keys !== [] && $keys !== null) {
                $this->redis->del(...$keys);
            }
        } while ($it > 0);
    }

    /**
     * 拼接带前缀的完整键名。
     */
    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * 序列化缓存值并落盘。
     *
     * 对 {@see MetaList} 优先尝试保留实例的原生序列化；若实例不可序列化
     * （极少见，例如属性构造函数里携带闭包 / 资源），则降级为「纯数据快照」
     * 并递归剥离其中的不可序列化值，保证写入永不抛异常。
     */
    private function pack(mixed $value): string
    {
        if ($value instanceof MetaList) {
            try {
                return serialize($value);
            } catch (Throwable) {
                return serialize(['__kode_snapshot' => $this->sanitize($value->toSnapshot())]);
            }
        }

        try {
            return serialize($value);
        } catch (Throwable) {
            // 非 MetaList 的不可序列化值：退化为字符串标记，确保不中断缓存写入。
            return serialize(['__kode_fallback' => is_object($value) ? get_class($value) : gettype($value)]);
        }
    }

    /**
     * 反序列化缓存值。
     *
     * 识别降级后的快照结构并还原为 {@see MetaList}（纯数据模式）。
     */
    private function unpack(string $raw): mixed
    {
        $data = unserialize($raw);

        if (is_array($data) && array_key_exists('__kode_snapshot', $data)) {
            /** @var array<int, array{name: string, args?: array<int|string, mixed>}> $snapshot */
            $snapshot = $data['__kode_snapshot'];

            return MetaList::fromSnapshot($snapshot);
        }

        if (is_array($data) && array_key_exists('__kode_fallback', $data)) {
            return $data['__kode_fallback'];
        }

        return $data;
    }

    /**
     * 递归清洗不可序列化的值（闭包、资源、含不可序列化字段的对象），
     * 以安全字符串占位，确保快照可被 `serialize()`。
     */
    private function sanitize(mixed $value): mixed
    {
        if ($value instanceof \Closure || is_resource($value)) {
            return '__kode_unserializable__';
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $k => $v) {
                $out[$k] = $this->sanitize($v);
            }

            return $out;
        }

        if (is_object($value)) {
            try {
                serialize($value);

                return $value;
            } catch (Throwable) {
                return '__kode_unserializable__';
            }
        }

        return $value;
    }

    /**
     * 写入 Redis，按 TTL 处理过期时间（<= 0 视为不过期）。
     */
    private function store(string $rk, string $payload): void
    {
        if ($this->ttl > 0) {
            $this->redis->set($rk, $payload, $this->ttl);
        } else {
            $this->redis->set($rk, $payload);
        }
    }
}
