<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Cache\RedisCache;
use Kode\Attributes\CacheInterface;
use Kode\Attributes\Meta;
use Kode\Attributes\MetaList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/FakeRedis.php';

#[CoversClass(RedisCache::class)]
final class RedisCacheTest extends TestCase
{
    private FakeRedis $redis;

    private RedisCache $cache;

    protected function setUp(): void
    {
        $this->redis = new FakeRedis();
        $this->cache = new RedisCache($this->redis, 'test:', 0);
    }

    #[Test]
    public function implementsCacheInterface(): void
    {
        $this->assertInstanceOf(CacheInterface::class, $this->cache);
    }

    #[Test]
    public function getInvokesLoaderOnceAndCaches(): void
    {
        $calls = 0;
        $loader = static function () use (&$calls) {
            ++$calls;

            return 'hello';
        };

        $first = $this->cache->get('k1', $loader);
        $second = $this->cache->get('k1', $loader);

        $this->assertSame('hello', $first);
        $this->assertSame('hello', $second);
        $this->assertSame(1, $calls, '命中缓存后 loader 不应再次被调用');
    }

    #[Test]
    public function hasAndDelete(): void
    {
        $this->assertFalse($this->cache->has('x'));
        $this->cache->set('x', [1, 2, 3]);
        $this->assertTrue($this->cache->has('x'));
        $this->cache->delete('x');
        $this->assertFalse($this->cache->has('x'));
    }

    #[Test]
    public function clearRemovesOnlyPrefixedKeys(): void
    {
        // 非本前缀的键应被保留（避免误删共享 Redis 中其它数据）
        $this->redis->set('foreign:key', 'v', 0);
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);

        $this->cache->clear();

        $this->assertTrue($this->redis->exists('foreign:key') > 0, '非前缀键必须存活');
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
    }

    #[Test]
    public function metaListInstancePreservedAcrossRoundTrip(): void
    {
        // 多进程 / 分布式场景关键：已实例化的属性对象在缓存取回后仍可返回实例。
        $meta = Meta::fromArray(['name' => SampleAttribute::class, 'args' => ['sample', 7]]);
        $meta->getInstance();
        $list = new MetaList([$meta]);

        $this->cache->set('ml', $list);
        $restored = $this->cache->get('ml', static fn () => null);

        $this->assertInstanceOf(MetaList::class, $restored);
        $this->assertCount(1, $restored);

        $restoredMeta = $restored->first();
        $this->assertSame(SampleAttribute::class, $restoredMeta->name);

        $inst = $restoredMeta->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $inst);
        $this->assertSame('sample', $inst->name);
        $this->assertSame(7, $inst->priority);
    }

    #[Test]
    public function nonSerializableInstanceDegradesToSnapshotWithoutThrowing(): void
    {
        // 属性实例不可序列化（此处携带闭包）时：缓存写入不得抛异常，
        // 且降级为快照后 name 仍可识别。
        $meta = Meta::fromArray(['name' => ClosureAttribute::class, 'args' => [static fn () => 42]]);
        $meta->getInstance();
        $list = new MetaList([$meta]);

        $this->cache->set('ml2', $list);

        $restored = $this->cache->get('ml2', static fn () => null);
        $this->assertInstanceOf(MetaList::class, $restored);
        $this->assertCount(1, $restored);
        $this->assertSame(ClosureAttribute::class, $restored->first()->name);
        // 降级后实例为 null，但重建因闭包丢失而失败 —— 应以 null 优雅返回而非抛异常。
        $this->assertNull($restored->first()->tryInstance());
    }
}
