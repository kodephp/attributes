<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Meta;
use Kode\Attributes\MetaList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(Meta::class)]
#[CoversClass(MetaList::class)]
final class MetaSnapshotTest extends TestCase
{
    #[Test]
    public function fromArrayIsNotReflected(): void
    {
        $meta = Meta::fromArray(['name' => SampleAttribute::class, 'args' => ['hello', 7]]);

        $this->assertFalse($meta->isReflected(), '纯数据模式不应标记为已反射');
        $this->assertEquals(SampleAttribute::class, $meta->name);
        $this->assertEquals('hello', $meta->getArgument(0));
        $this->assertEquals(7, $meta->getArgument(1));
    }

    #[Test]
    public function fromArrayCanInstantiate(): void
    {
        $meta = Meta::fromArray(['name' => SampleAttribute::class, 'args' => ['snap', 3]]);

        $instance = $meta->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instance);
        $this->assertEquals('snap', $instance->name);
        $this->assertEquals(3, $instance->priority);
    }

    #[Test]
    public function snapshotRoundTrips(): void
    {
        $meta = AttrHelper::sampleMeta();
        $snapshot = $meta->toSnapshot();

        $this->assertArrayHasKey('name', $snapshot);
        $this->assertArrayHasKey('args', $snapshot);

        $restored = Meta::fromArray($snapshot);
        $this->assertEquals($meta->name, $restored->name);
        $this->assertEquals($meta->args, $restored->args);
    }

    #[Test]
    public function jsonSerializeWorksWithoutReflection(): void
    {
        $meta = AttrHelper::sampleMeta();
        $json = json_encode($meta);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals(SampleAttribute::class, $decoded['name']);
    }

    #[Test]
    public function serializeUnserializeRoundTrips(): void
    {
        $meta = AttrHelper::sampleMeta();
        $serialized = serialize($meta);

        // 关键：ReflectionAttribute 不可序列化，序列化必须丢弃反射对象而不报错
        $this->assertIsString($serialized);

        $restored = unserialize($serialized);
        $this->assertInstanceOf(Meta::class, $restored);
        $this->assertFalse($restored->isReflected());
        $this->assertEquals($meta->name, $restored->name);
        $this->assertEquals($meta->args, $restored->args);

        // 还原后仍能实例化（纯数据模式）
        $instance = $restored->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instance);
    }

    #[Test]
    public function metaListSnapshotRoundTrips(): void
    {
        $list = Attr::of(SampleClass::class);

        $snapshot = $list->toSnapshot();
        $this->assertIsArray($snapshot);
        $this->assertNotEmpty($snapshot);

        $restored = MetaList::fromSnapshot($snapshot);
        $this->assertInstanceOf(MetaList::class, $restored);
        $this->assertEquals($list->count(), $restored->count());
        $this->assertTrue($restored->has(SampleAttribute::class));
    }
}

/**
 * 测试辅助：提供基于反射的样例 Meta，避免在每个测试里重复构造 ReflectionAttribute。
 */
final class AttrHelper
{
    public static function sampleMeta(): Meta
    {
        $attributes = (new \ReflectionClass(SampleClass::class))
            ->getAttributes(SampleAttribute::class);

        return new Meta($attributes[0]);
    }
}
