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
require_once __DIR__ . '/Fixtures.php';

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

    #[Test]
    public function metaListIsStoredAsPureDataSnapshot(): void
    {
        // 落盘载荷必须是纯数据快照：受限解码（allowed_classes => false）后不得出现任何对象。
        $list = new MetaList([Meta::fromArray(['name' => SampleAttribute::class, 'args' => ['s', 1]])]);
        $list->first()?->getInstance();

        $this->cache->set('raw', $list);

        $stored = $this->redis->get('test:raw');
        $this->assertIsString($stored);
        $this->assertStringContainsString('__kode_snapshot', $stored);
        $this->assertStringNotContainsString('MetaList', $stored, '不得写入 MetaList 的原生对象序列化');

        $decoded = @unserialize($stored, ['allowed_classes' => false]);
        $this->assertIsArray($decoded);
        $this->assertFalse($this->hasIncomplete($decoded), '受限解码后不得出现残缺对象');
    }

    #[Test]
    public function enumArgumentsSurviveRoundTrip(): void
    {
        // 枚举是属性参数里的常见形态：受限解码下必须原样取回，否则业务读到的是残缺值。
        $list = new MetaList([
            Meta::fromArray(['name' => EnumArgAttribute::class, 'args' => [RoleEnum::Admin]]),
        ]);

        $this->cache->set('enum', $list);
        $restored = $this->cache->get('enum', static fn () => null);

        $this->assertInstanceOf(MetaList::class, $restored);
        $instance = $restored->first()?->getInstance();
        $this->assertInstanceOf(EnumArgAttribute::class, $instance);
        $this->assertSame(RoleEnum::Admin, $instance->role);
    }

    #[Test]
    public function payloadWithUnrestorableObjectSkipsSharedWrite(): void
    {
        // 嵌套属性实例在受限解码下会变成残缺对象：与其落一份失真数据，不如不写。
        $list = new MetaList([
            Meta::fromArray([
                'name' => NestedAttrArgument::class,
                'args' => [new SampleAttribute('inner', 3)],
            ]),
        ]);

        $this->cache->set('nested', $list);

        $this->assertFalse($this->cache->has('nested'), '不可还原的载荷不得写入共享缓存');
    }

    #[Test]
    public function corruptedPayloadIsTreatedAsMissAndPurged(): void
    {
        // 外部写入的垃圾载荷：按未命中处理、删键重建，绝不返回半成品。
        $this->redis->set('test:bad', 'not-serialized-at-all');

        $calls = 0;
        $loader = static function () use (&$calls) {
            ++$calls;

            return 'rebuilt';
        };

        $this->assertSame('rebuilt', $this->cache->get('bad', $loader));
        $this->assertSame(1, $calls);
        $this->assertSame('rebuilt', $this->cache->get('bad', $loader));
        $this->assertSame(1, $calls, '重建后的载荷应可正常命中');
    }

    #[Test]
    public function externallySerializedObjectNeverGetsInstantiated(): void
    {
        // 对象注入面：共享 Redis 被写入自定义类的序列化串时，解码不得进入该类的反序列化钩子。
        $poisoned = serialize(new PoisonGadget('boom'));
        PoisonGadget::$woke = 0;
        $this->redis->set('test:gadget', $poisoned);

        $value = $this->cache->get('gadget', static fn () => 'safe');

        $this->assertSame(0, PoisonGadget::$woke, '受限解码不得实例化任何类');
        $this->assertSame('safe', $value);
        $this->assertSame('safe', $this->cache->get('gadget', static fn () => 'safe'));
    }

    #[Test]
    public function malformedSnapshotStructureIsRejected(): void
    {
        // 快照键存在但内容不是「name/args」结构：判为未命中，不能把脏数据塞进 MetaList。
        $this->redis->set('test:snap', serialize(['__kode_snapshot' => 'garbage']));
        $this->assertSame('x', $this->cache->get('snap', static fn () => 'x'));

        $this->redis->set('test:snap2', serialize(['__kode_snapshot' => [['name' => 123]]]));
        $this->assertSame('y', $this->cache->get('snap2', static fn () => 'y'));

        $this->redis->set('test:snap3', serialize(['__kode_snapshot' => [['name' => SampleAttribute::class, 'args' => 'nope']]]));
        $this->assertSame('z', $this->cache->get('snap3', static fn () => 'z'));
    }

    #[Test]
    public function legacyNativeEntrySelfHeals(): void
    {
        // 旧版本写入的原生 MetaList：受限解码得到残缺对象 → 未命中 → 以快照重建。
        $legacy = serialize(new MetaList([
            Meta::fromArray(['name' => SampleAttribute::class, 'args' => ['legacy', 9]]),
        ]));
        $this->redis->set('test:legacy', $legacy);

        $restored = $this->cache->get('legacy', static fn (): MetaList => new MetaList([
            Meta::fromArray(['name' => SampleAttribute::class, 'args' => ['legacy', 9]]),
        ]));

        $this->assertInstanceOf(MetaList::class, $restored);
        $this->assertSame(SampleAttribute::class, $restored->first()?->name);
        $this->assertStringContainsString('__kode_snapshot', (string) $this->redis->get('test:legacy'));
    }

    #[Test]
    public function falseValueIsCachedAndNotReloaded(): void
    {
        // unserialize('b:0;') 同样返回 false：必须能区分「值为 false」与「解码失败」。
        $calls = 0;
        $loader = static function () use (&$calls) {
            ++$calls;

            return false;
        };

        $this->assertFalse($this->cache->get('no', $loader));
        $this->assertFalse($this->cache->get('no', $loader));
        $this->assertSame(1, $calls, '缓存 false 后不应再次回源');
    }

    #[Test]
    public function objectValueSkipsSharedWrite(): void
    {
        // 非 MetaList 的对象值无法在受限解码下还原：静默跳过，而不是落一份残缺数据。
        $this->cache->set('obj', new \stdClass());

        $this->assertFalse($this->cache->has('obj'));
    }

    #[Test]
    public function nestedArraysRoundTrip(): void
    {
        $payload = ['routes' => [['path' => '/a', 'methods' => ['GET']]], 'flag' => true];

        $this->cache->set('arr', $payload);

        $this->assertSame($payload, $this->cache->get('arr', static fn () => null));
    }

    /**
     * 递归探测受限解码产生的残缺对象（正常应为 false）。
     */
    private function hasIncomplete(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasIncomplete($item)) {
                    return true;
                }
            }
        }

        return $value instanceof \__PHP_Incomplete_Class;
    }
}
