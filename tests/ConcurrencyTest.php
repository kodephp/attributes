<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Cache\RedisCache;
use Kode\Attributes\Meta;
use Kode\Attributes\MetaList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/FakeRedis.php';

#[CoversClass(Attr::class)]
#[CoversClass(RedisCache::class)]
final class ConcurrencyTest extends TestCase
{
    private FakeRedis $redis;

    private RedisCache $sharedCache;

    protected function setUp(): void
    {
        $this->redis = new FakeRedis();
        $this->sharedCache = new RedisCache($this->redis, 'app:v2:', 0);
        // 模拟「多进程 / 分布式」共享缓存：进程启动时挂载同一 Redis 缓存。
        Attr::setCache($this->sharedCache);
        Attr::strict(false);
    }

    protected function tearDown(): void
    {
        Attr::clear();
    }

    #[Test]
    public function attrReadsGoThroughSharedCache(): void
    {
        $list = Attr::of(SampleClass::class);
        $this->assertGreaterThan(0, count($list));

        $keys = array_keys($this->redis->dumpStore());
        $this->assertNotEmpty($keys, '首次读取必须写入共享缓存');
        $this->assertStringStartsWith('app:v2:', $keys[0]);
    }

    #[Test]
    public function crossProcessReadHitsSharedCacheWithInstances(): void
    {
        // 进程 A：写入共享缓存
        $listA = Attr::of(SampleClass::class);
        $instA = $listA->first()->getInstance();

        // 模拟新进程 B：重建 Reader 并挂载同一共享缓存
        Attr::setCache($this->sharedCache);
        $listB = Attr::of(SampleClass::class);

        $this->assertCount($listA->count(), $listB, '跨进程读取应命中同一缓存，结果一致');

        $instB = $listB->first()->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instB);
        $this->assertSame($instA->name, $instB->name, '缓存取回的属性实例应可被复用');
    }

    #[Test]
    public function clearCacheInvalidatesSharedStore(): void
    {
        Attr::of(SampleClass::class);
        $this->assertNotEmpty($this->redis->dumpStore());

        // fork 后的子进程清空自身缓存（避免读到父进程残留）
        Attr::clearCache();

        $this->assertEmpty($this->redis->dumpStore(), 'clearCache 必须清空共享缓存中的本前缀键');
    }

    #[Test]
    public function attributeReadingWorksInsideFiber(): void
    {
        // 协程 / Fibers 场景下，属性读取应保持可重入与一致性。
        // 若当前 PHP 构建未启用可用的 Fiber（部分 NTS 发行版如此），则跳过本验证。
        if (!self::fibersSupported()) {
            $this->markTestSkipped('当前 PHP 构建的 Fiber 不可用，跳过协程读取验证');
        }

        $count = null;
        $fiber = new \Fiber(static function () use (&$count): void {
            $count = Attr::of(SampleClass::class)->count();
            \Fiber::suspend($count);
        });
        $result = $fiber->resume();

        $this->assertGreaterThan(0, $result);
        $this->assertSame($result, $count);
    }

    /**
     * 探测当前运行环境是否支持可用的 Fiber。
     */
    private static function fibersSupported(): bool
    {
        try {
            $f = new \Fiber(static fn () => \Fiber::suspend(1));

            return $f->resume() === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
