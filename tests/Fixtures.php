<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Attribute;

/*
 * 共享测试夹具。
 * 本文件不是 *Test.php，PHPUnit 不会把它当作测试套件加载；
 * 各测试文件通过 require_once 引入，所有夹具类在同一命名空间下声明一次，避免重复声明。
 */

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
class SampleAttribute
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0
    ) {
    }
}

#[Attribute(Attribute::TARGET_CLASS)]
class ClassMarker
{
    public function __construct(public readonly string $label = 'class') {}
}

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class InjectAttribute
{
    public function __construct(public readonly ?string $service = null) {}
}

#[Attribute(Attribute::TARGET_METHOD)]
class MethodMarker
{
    public function __construct(public readonly string $name = 'method') {}
}

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class ConstMarker
{
    public function __construct(public readonly string $role = 'const') {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class ParamMarker
{
    public function __construct(public readonly string $rule = 'required') {}
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class CombinedAttribute
{
    public function __construct(public readonly string $tag = 'combined') {}
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::TARGET_CLASS_CONSTANT)]
class UniversalAttribute
{
    public function __construct(public readonly string $note = 'universal') {}
}

#[Attribute(Attribute::TARGET_FUNCTION)]
class FunctionMarker
{
    public function __construct(public readonly string $name = 'fn') {}
}

#[Attribute(Attribute::TARGET_CLASS)]
class BrokenAttribute
{
    public function __construct()
    {
        throw new \RuntimeException('attribute constructor boom');
    }
}

#[SampleAttribute('test-class', 10)]
#[SampleAttribute('another-class', 20)]
class SampleClass
{
    #[SampleAttribute('test-property')]
    private string $property = '';

    #[SampleAttribute('test-method', 5)]
    public function sampleMethod(): void {}

    public function methodWithParam(#[ParamMarker('id')] int $id): void {}
}

class SampleClassWithConstant
{
    #[SampleAttribute('constant-attr')]
    public const SAMPLE_CONSTANT = 'value';
}

#[ClassMarker('base')]
class InheritanceBase
{
    #[InjectAttribute('base-secret')]
    private string $secret = '';

    #[MethodMarker('base-method')]
    public function baseMethod(): void {}
}

#[ClassMarker('child')]
class InheritanceChild extends InheritanceBase
{
    #[InjectAttribute('child-prop')]
    public string $childProp = '';

    // 覆写基类方法但自身不带属性，用于验证继承链上溯
    public function baseMethod(): void {}
}

#[BrokenAttribute]
class ClassWithBroken
{
}

enum RoleEnum: string
{
    #[ConstMarker('admin-role')]
    case Admin = 'admin';

    #[ConstMarker('guest-role')]
    case Guest = 'guest';

    #[ConstMarker('user-role')]
    case User = 'user';
}

class CollisionFoo
{
    #[SampleAttribute('foo-bar-method')]
    public function bar(): void {}
}

class CollisionBaz
{
    #[SampleAttribute('baz-bar-method')]
    public function bar(): void {}
}
