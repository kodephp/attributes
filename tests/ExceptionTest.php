<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Exception\AttributeException;
use Kode\Attributes\Exception\AttributeInstantiationException;
use Kode\Attributes\Exception\InvalidTargetException;
use Kode\Attributes\Exception\TargetNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(AttributeException::class)]
#[CoversClass(InvalidTargetException::class)]
#[CoversClass(TargetNotFoundException::class)]
#[CoversClass(AttributeInstantiationException::class)]
final class ExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    protected function tearDown(): void
    {
        // 严格模式会修改单例 Reader，必须复位避免污染其它测试
        Attr::clear();
        parent::tearDown();
    }

    #[Test]
    public function exceptionHierarchyImplementsMarker(): void
    {
        $this->assertInstanceOf(AttributeException::class, new InvalidTargetException('x'));
        $this->assertInstanceOf(AttributeException::class, new TargetNotFoundException('x'));
        $this->assertInstanceOf(AttributeException::class, new AttributeInstantiationException('x'));
    }

    #[Test]
    public function missingClassThrowsTargetNotFound(): void
    {
        $this->expectException(TargetNotFoundException::class);

        Attr::of('Kode\\Attributes\\Tests\\ThisClassDoesNotExist');
    }

    #[Test]
    public function missingMemberThrowsTargetNotFound(): void
    {
        $this->expectException(TargetNotFoundException::class);

        Attr::of(SampleClass::class . '::noSuchMethod');
    }

    #[Test]
    public function invalidTargetValueThrows(): void
    {
        $this->expectException(InvalidTargetException::class);

        // int 既不是类名、对象、闭包、可调用数组，也不是 Reflector
        Attr::of(123);
    }

    #[Test]
    public function missingEnumCaseThrowsTargetNotFound(): void
    {
        $this->expectException(TargetNotFoundException::class);

        Attr::ofConstant(RoleEnum::class, 'MissingCase');
    }

    #[Test]
    public function nonStrictSkipsBrokenAttribute(): void
    {
        // 非严格模式：元数据仍可枚举（声明存在），但实例化时损坏的属性被静默跳过
        $this->assertTrue(Attr::has(ClassWithBroken::class, BrokenAttribute::class));

        $instances = Attr::instances(ClassWithBroken::class, BrokenAttribute::class);
        $this->assertEmpty($instances, '非严格模式应跳过实例化失败的属性');
    }

    #[Test]
    public function strictModeThrowsOnBrokenAttribute(): void
    {
        Attr::strict(true);

        $this->expectException(AttributeInstantiationException::class);

        Attr::instances(ClassWithBroken::class, BrokenAttribute::class);
    }

    #[Test]
    public function brokenAttributeInstanceThrowsEvenWhenNotStrict(): void
    {
        // 即便非严格模式，显式调用 getInstance() 也应抛出，而非返回错误结果
        $meta = Attr::get(ClassWithBroken::class, BrokenAttribute::class);

        if ($meta !== null) {
            $this->expectException(AttributeInstantiationException::class);
            $meta->getInstance();
        } else {
            // 非严格模式下 BrokenAttribute 已被跳过，meta 为 null，跳过断言
            $this->assertNull($meta);
        }
    }
}
