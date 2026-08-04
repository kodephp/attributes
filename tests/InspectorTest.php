<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Inspector;
use Kode\Attributes\Meta;
use Kode\Attributes\MetaList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(Inspector::class)]
#[CoversClass(Attr::class)]
final class InspectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    #[Test]
    public function onReturnsInspector(): void
    {
        $this->assertInstanceOf(Inspector::class, Attr::on(SampleClass::class));
    }

    #[Test]
    public function attributesAndHas(): void
    {
        $ins = Attr::on(SampleClass::class);

        $this->assertInstanceOf(MetaList::class, $ins->attributes());
        $this->assertTrue($ins->has(SampleAttribute::class));
        $this->assertInstanceOf(Meta::class, $ins->get(SampleAttribute::class));
        $this->assertInstanceOf(MetaList::class, $ins->all(SampleAttribute::class));
        $this->assertInstanceOf(SampleAttribute::class, $ins->instance(SampleAttribute::class));
    }

    #[Test]
    public function describeClass(): void
    {
        $ins = Attr::on(SampleClass::class);
        $this->assertStringContainsString('类', $ins->describe());
        $this->assertStringContainsString('SampleClass', $ins->describe());
    }

    #[Test]
    public function methodAndPropertyAccess(): void
    {
        $ins = Attr::on(SampleClass::class);

        $methodList = $ins->method('sampleMethod');
        $this->assertTrue($methodList->has(SampleAttribute::class));

        $propList = $ins->property('property');
        $this->assertTrue($propList->has(SampleAttribute::class));
    }

    #[Test]
    public function constantAccess(): void
    {
        $ins = Attr::on(SampleClassWithConstant::class);

        $constList = $ins->constant('SAMPLE_CONSTANT');
        $this->assertTrue($constList->has(SampleAttribute::class));
    }

    #[Test]
    public function inheritedModeCollectsParentPrivateProperty(): void
    {
        $props = Attr::on(InheritanceChild::class)
            ->inherited()
            ->properties(InjectAttribute::class);

        $this->assertArrayHasKey('secret', $props, '链式 inherited() 必须收集到父类私有属性');
        $this->assertArrayHasKey('childProp', $props);
    }

    #[Test]
    public function reflectorResolvesTarget(): void
    {
        $ins = Attr::on(SampleClass::class);
        $ref = $ins->reflector();

        $this->assertInstanceOf(\ReflectionClass::class, $ref);
        $this->assertEquals(SampleClass::class, $ref->getName());
    }
}
