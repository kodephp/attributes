<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

/*
 * 内存版 Redis 模拟器，用于在无 Redis 服务依赖下确定性地测试 RedisCache。
 * 仅实现本包实际用到的命令（get/set/exists/del/scan），签名与 phpredis 保持一致，
 * 因此可作为 \Redis 的替身注入到 {@see \Kode\Attributes\Cache\RedisCache}。
 *
 * 本文件不是 *Test.php，PHPUnit 不会将其当作测试套件加载。
 */

final class FakeRedis extends \Redis
{
    /** @var array<string, string> 以「完整键名」为索引的内存存储 */
    private array $store = [];

    #[\Override]
    public function get(string $key): mixed
    {
        return $this->store[$key] ?? false;
    }

    #[\Override]
    public function set(string $key, mixed $value, mixed $options = null): \Redis|string|bool
    {
        $this->store[$key] = $value;

        return true;
    }

    #[\Override]
    public function exists(mixed $key, mixed ...$other_keys): \Redis|int|bool
    {
        $keys = is_array($key) ? $key : array_merge([$key], $other_keys);
        $n = 0;

        foreach ($keys as $k) {
            if (array_key_exists((string) $k, $this->store)) {
                ++$n;
            }
        }

        return $n;
    }

    #[\Override]
    public function del(array|string $key, string ...$other_keys): \Redis|int|false
    {
        $keys = is_array($key) ? $key : array_merge([$key], $other_keys);
        $n = 0;

        foreach ($keys as $k) {
            $k = (string) $k;

            if (array_key_exists($k, $this->store)) {
                unset($this->store[$k]);
                ++$n;
            }
        }

        return $n;
    }

    #[\Override]
    public function scan(string|int|null &$iterator, ?string $pattern = null, ?int $count = null, ?string $type = null): array|false
    {
        if ($iterator === null || $iterator === 0) {
            $iterator = 0;
        }

        $keys = array_keys($this->store);

        if ($pattern !== null && $pattern !== '*') {
            $re = '/^' . str_replace('\\*', '.*', preg_quote((string) $pattern, '/')) . '$/';
            $keys = preg_grep($re, $keys);
        }

        $keys = array_values($keys);
        $page = array_slice($keys, $iterator, (int) ($count ?? 10));

        if ($page === []) {
            $iterator = 0;

            return false;
        }

        $iterator += count($page);

        return $page;
    }

    /** 测试辅助：导出当前存储内容 */
    public function dumpStore(): array
    {
        return $this->store;
    }

    /** 测试辅助：清空存储 */
    public function flushStore(): void
    {
        $this->store = [];
    }
}
