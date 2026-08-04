<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Reader;
use Kode\Attributes\Meta;
use Kode\Attributes\MetaList;
use Kode\Attributes\TargetRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(TargetRef::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Attr::class)]
final class ReflectionTargetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    #[Test]
    public function reflectionClassIsReadAsTargetNotAsObject(): void
    {
        // 旧版根因：传入 ReflectionClass 会被当作普通对象，转而读取 Reflection 类自身属性 -> 永远空集合。
        // 2.0：ReflectionClass 透传为「目标」，应返回 SampleClass 的类级属性。
        $ref = new \ReflectionClass(SampleClass::class);

        $viaReflection = Attr::of($ref);
        $viaString = Attr::of(SampleClass::class);

        $this->assertInstanceOf(MetaList::class, $viaReflection);
        $this->assertGreaterThan(0, count($viaReflection), 'ReflectionClass 必须返回目标类的属性，而非空集合');
        $this->assertTrue($viaReflection->has(SampleAttribute::class));
        $this->assertEquals($viaString->count(), $viaReflection->count());
    }

    #[Test]
    public function reflectionPropertyReadsPropertyAttributes(): void
    {
        $ref = new \ReflectionProperty(SampleClass::class, 'property');

        $list = Attr::of($ref);

        $this->assertInstanceOf(MetaList::class, $list);
        $this->assertGreaterThan(0, count($list));
        $this->assertTrue($list->has(SampleAttribute::class));

        $instance = $list->first()->getInstance();
        $this->assertEquals('test-property', $instance->name);
    }

    #[Test]
    public function reflectionMethodReadsMethodAttributes(): void
    {
        $ref = new \ReflectionMethod(SampleClass::class, 'sampleMethod');

        $list = Attr::of($ref);

        $this->assertInstanceOf(MetaList::class, $list);
        $this->assertTrue($list->has(SampleAttribute::class));

        $instance = $list->first()->getInstance();
        $this->assertEquals('test-method', $instance->name);
        $this->assertEquals(5, $instance->priority);
    }

    #[Test]
    public function reflectionParameterReadsParameterAttributes(): void
    {
        $ref = new \ReflectionParameter([SampleClass::class, 'methodWithParam'], 'id');

        $list = Attr::of($ref);

        $this->assertInstanceOf(MetaList::class, $list);
        $this->assertTrue($list->has(ParamMarker::class));

        $meta = $list->first();
        $this->assertInstanceOf(Meta::class, $meta);
        $this->assertEquals('id', $meta->getArgument('rule'));
    }

    #[Test]
    public function readerReadAcceptsReflectorDirectly(): void
    {
        $reader = new Reader();
        $ref = new \ReflectionClass(SampleClass::class);

        $list = $reader->read($ref);

        $this->assertTrue($list->has(SampleAttribute::class));
    }

    #[Test]
    public function closureResolvesToReflectionFunction(): void
    {
        $fn = function () {};

        $ref = TargetRef::resolve($fn);
        $this->assertInstanceOf(\ReflectionFunction::class, $ref);
    }

    #[Test]
    public function callableArrayResolvesToReflectionMethod(): void
    {
        $ref = TargetRef::resolve([SampleClass::class, 'sampleMethod']);
        $this->assertInstanceOf(\ReflectionMethod::class, $ref);
        $this->assertEquals('sampleMethod', $ref->getName());
    }

    #[Test]
    public function stringMemberExpressionsResolve(): void
    {
        $methodRef = TargetRef::resolve(SampleClass::class . '::sampleMethod');
        $this->assertInstanceOf(\ReflectionMethod::class, $methodRef);

        $propRef = TargetRef::resolve(SampleClass::class . '::$property');
        $this->assertInstanceOf(\ReflectionProperty::class, $propRef);

        $constRef = TargetRef::resolve(SampleClassWithConstant::class . '::SAMPLE_CONSTANT');
        $this->assertInstanceOf(\ReflectionClassConstant::class, $constRef);

        $paramRef = TargetRef::resolve(SampleClass::class . '::methodWithParam($id)');
        $this->assertInstanceOf(\ReflectionParameter::class, $paramRef);
        $this->assertEquals('id', $paramRef->getName());
    }
}
