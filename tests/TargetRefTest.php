<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Attribute;
use Kode\Attributes\Target;
use Kode\Attributes\TargetSet;
use Kode\Attributes\TargetRef;
use Kode\Attributes\Meta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(TargetRef::class)]
#[CoversClass(TargetSet::class)]
#[CoversClass(Target::class)]
#[CoversClass(Meta::class)]
final class TargetRefTest extends TestCase
{
    #[Test]
    public function resolvePassesThroughReflector(): void
    {
        $ref = new \ReflectionClass(SampleClass::class);
        $this->assertSame($ref, TargetRef::resolve($ref));
    }

    #[Test]
    public function keyForAvoidsCrossClassCollision(): void
    {
        $foo = new \ReflectionMethod(CollisionFoo::class, 'bar');
        $baz = new \ReflectionMethod(CollisionBaz::class, 'bar');

        $keyFoo = TargetRef::keyFor($foo);
        $keyBaz = TargetRef::keyFor($baz);

        $this->assertStringContainsString('CollisionFoo::bar', $keyFoo);
        $this->assertStringContainsString('CollisionBaz::bar', $keyBaz);
        $this->assertNotEquals($keyFoo, $keyBaz, '不同类同名方法必须生成不同的缓存键');
    }

    #[Test]
    public function keyForEscapesAnonymousClassNulBytes(): void
    {
        $anonymous = new \ReflectionClass(new class () {});
        $key = TargetRef::keyFor($anonymous);

        $this->assertIsString($key);
        $this->assertStringNotContainsString("\0", $key, '匿名类键中的 NUL 字节必须转义');
    }

    #[Test]
    public function targetSetExpressesCombinationWithoutDegradingToAll(): void
    {
        $set = TargetSet::of(Target::Clazz, Target::Method);

        $this->assertEquals(5, $set->mask);
        $this->assertTrue($set->has(Target::Clazz));
        $this->assertTrue($set->has(Target::Method));
        $this->assertFalse($set->has(Target::Property));
        $this->assertCount(2, $set->targets());
        $this->assertFalse($set->isAll());
    }

    #[Test]
    public function targetSetFromAttributeFlags(): void
    {
        $set = TargetSet::fromAttributeFlags(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY);

        $this->assertTrue($set->has(Target::Clazz));
        $this->assertTrue($set->has(Target::Method));
        $this->assertTrue($set->has(Target::Property));
        $this->assertFalse($set->has(Target::Parameter));
    }

    #[Test]
    public function targetSetOperations(): void
    {
        $base = TargetSet::of(Target::Clazz);

        $with = $base->with(Target::Method);
        $this->assertTrue($with->has(Target::Method));

        $without = $with->without(Target::Clazz);
        $this->assertFalse($without->has(Target::Clazz));
        $this->assertTrue($without->has(Target::Method));

        $merged = TargetSet::of(Target::Clazz)->merge(TargetSet::of(Target::Method));
        $this->assertTrue($merged->has(Target::Clazz));
        $this->assertTrue($merged->has(Target::Method));

        $intersect = $merged->intersect(TargetSet::of(Target::Clazz, Target::Property));
        $this->assertTrue($intersect->has(Target::Clazz));
        $this->assertFalse($intersect->has(Target::Method));
    }

    #[Test]
    public function targetSetSupportsAndIntersects(): void
    {
        $combined = TargetSet::of(Target::Clazz, Target::Method);

        $this->assertTrue($combined->supports(Target::Clazz));
        $this->assertFalse($combined->supports(Target::Property));

        $this->assertTrue($combined->intersects(Target::Method));
        $this->assertTrue($combined->intersects(TargetSet::of(Target::Clazz)));
        $this->assertFalse($combined->intersects(Target::Property));
    }

    #[Test]
    public function combinedAttributeMetaUsesPreciseTargetSet(): void
    {
        // CombinedAttribute 自身声明为 类 | 方法；1.x 的 Target 枚举会退化成 All，
        // 2.0 用 TargetSet 精确表达组合目标。
        $meta = Meta::fromArray(['name' => CombinedAttribute::class]);

        $set = $meta->getTargetSet();
        $this->assertTrue($set->has(Target::Clazz));
        $this->assertTrue($set->has(Target::Method));
        $this->assertFalse($set->has(Target::Property));
        $this->assertFalse($set->isAll());

        $this->assertTrue($meta->supportsTarget(Target::Method));
        $this->assertFalse($meta->supportsTarget(Target::Property));

        // getTarget() 无法精确表达组合时回退到 All（这是预期行为）
        $this->assertEquals(Target::All, $meta->getTarget());
    }

    #[Test]
    public function describeFormatsEachKind(): void
    {
        $this->assertStringContainsString('类', TargetRef::describe(new \ReflectionClass(SampleClass::class)));
        $this->assertStringContainsString('方法', TargetRef::describe(new \ReflectionMethod(SampleClass::class, 'sampleMethod')));
        $this->assertStringContainsString('属性', TargetRef::describe(new \ReflectionProperty(SampleClass::class, 'property')));
        $this->assertStringContainsString('参数', TargetRef::describe(new \ReflectionParameter([SampleClass::class, 'methodWithParam'], 'id')));
    }
}
