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
 * 只缓存数据，不缓存对象（自 v2.2.0 起的契约）：
 * - 读取一律走 `unserialize($raw, ['allowed_classes' => false])`，共享 Redis 被外部写入时
 *   也不会实例化任何类（对象注入 / POP 链）；枚举因不可被构造而能安全原样取回。
 * - 与之配对，{@see MetaList} 恒以 `toSnapshot()` 的纯数据形态落盘，取回后仍可按需
 *   `Meta::getInstance()` 惰性构造属性实例，语义不变、少一次反射。
 * - 载荷里含「受限解码后取不回原样」的对象（嵌套属性实例等）时**跳过写入**：
 *   宁可本进程多一次反射，也不落一份取回即失真的数据。
 * - 解码失败 / 结构不合规 / 含残缺对象的载荷一律按未命中处理并删除键，绝不返回半成品，
 *   因此历史版本写入的原生序列化条目会自动失效重建。
 * - 该适配器依赖 `ext-redis`；无 Redis 环境仍可使用默认的 {@see \Kode\Attributes\ArrayCache}（进程内）。
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
     * 纯数据快照的结构键。
     */
    private const string SNAPSHOT_KEY = '__kode_snapshot';

    /**
     * 布尔 false 的序列化形态（`unserialize()` 失败时也返回 false，需据此区分）。
     */
    private const string SERIALIZED_FALSE = 'b:0;';

    /**
     * 不可序列化值（闭包 / 资源）的占位串，保持「写入永不抛异常」的历史语义。
     */
    private const string UNSERIALIZABLE = '__kode_unserializable__';

    /**
     * 递归校验的深度上限：载荷可能来自外部写入，自引用数组会把递归变成死循环。
     */
    private const int MAX_NEST_DEPTH = 16;

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

        if (is_string($raw)) {
            $cached = null;

            if ($this->unpack($raw, $cached)) {
                return $cached;
            }

            // 载荷不可还原（外部写入污染 / 历史版本格式）：删键重建，绝不返回半成品
            $this->redis->del($rk);
        }

        $value = $loader();
        $payload = $this->pack($value);

        if ($payload !== null) {
            $this->store($rk, $payload);
        }

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
     *
     * 值无法在「受限解码」下原样取回时静默跳过写入。
     */
    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $payload = $this->pack($value);

        if ($payload !== null) {
            $this->store($this->key($key), $payload);
        }
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

            if ($keys !== []) {
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
     * 把缓存值打包为「受限解码后仍能原样取回」的载荷。
     *
     * @return string|null 载荷；null 表示该值不该进共享缓存（跳过写入）
     */
    private function pack(mixed $value): ?string
    {
        if ($value instanceof MetaList) {
            $snapshot = $value->toSnapshot();

            if ($this->hasUnrestorableObject($snapshot)) {
                return null;
            }

            /** @var array<int, array<string, mixed>> $cleaned */
            $cleaned = $this->stripUnserializable($snapshot);

            return serialize([self::SNAPSHOT_KEY => $cleaned]);
        }

        if ($this->hasUnrestorableObject($value)) {
            return null;
        }

        return $this->trySerialize($value);
    }

    /**
     * 受限反序列化，把载荷还原为缓存值。
     *
     * @param-out mixed $value
     * @return bool false 表示载荷不可信或不可还原，调用方应按未命中处理
     */
    private function unpack(string $raw, mixed &$value): bool
    {
        if ($raw === self::SERIALIZED_FALSE) {
            $value = false;

            return true;
        }

        $data = @unserialize($raw, ['allowed_classes' => false]);

        if ($data === false) {
            return false;
        }

        if (is_array($data) && array_key_exists(self::SNAPSHOT_KEY, $data)) {
            if (!$this->validSnapshot($data[self::SNAPSHOT_KEY])) {
                return false;
            }

            /** @var array<int, array{name: string, args?: array<int|string, mixed>}> $snapshot */
            $snapshot = $data[self::SNAPSHOT_KEY];
            $value = MetaList::fromSnapshot($snapshot);

            return true;
        }

        if ($this->hasUnrestorableObject($data)) {
            return false;
        }

        $value = $data;

        return true;
    }

    /**
     * 快照结构校验：每项都必须是「name 为字符串、args 为纯数据」的数组。
     *
     * 结构不合规说明载荷不是本类写出的（或来自能裸还原对象的旧版本），按未命中处理。
     */
    private function validSnapshot(mixed $payload, int $depth = 0): bool
    {
        if (!is_array($payload) || $depth > self::MAX_NEST_DEPTH) {
            return false;
        }

        foreach ($payload as $item) {
            if (!is_array($item) || !isset($item['name']) || !is_string($item['name'])) {
                return false;
            }

            if (array_key_exists('args', $item)
                && (!is_array($item['args']) || $this->hasUnrestorableObject($item['args'], $depth + 1))) {
                return false;
            }
        }

        return true;
    }

    /**
     * 递归探测「写进共享缓存就取不回原样」的值。
     *
     * 非枚举对象在受限解码下会变成残缺对象（解码侧），或本就无跨进程形态（编码侧）；
     * 闭包 / 资源另按历史契约以占位串降级，不在此列。
     */
    private function hasUnrestorableObject(mixed $value, int $depth = 0): bool
    {
        if ($depth > self::MAX_NEST_DEPTH) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasUnrestorableObject($item, $depth + 1)) {
                    return true;
                }
            }

            return false;
        }

        return is_object($value) && !$value instanceof \Closure && !$value instanceof \UnitEnum;
    }

    /**
     * 递归替换闭包 / 资源为占位串，保证快照写入不抛异常。
     */
    private function stripUnserializable(mixed $value): mixed
    {
        if ($value instanceof \Closure || is_resource($value)) {
            return self::UNSERIALIZABLE;
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $k => $v) {
                $out[$k] = $this->stripUnserializable($v);
            }

            return $out;
        }

        return $value;
    }

    /**
     * 尝试序列化，失败返回 null（调用方跳过写入）。
     */
    private function trySerialize(mixed $value): ?string
    {
        try {
            return serialize($value);
        } catch (Throwable) {
            return null;
        }
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
