<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Reader;
use Kode\Attributes\MetaList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(Reader::class)]
#[CoversClass(Attr::class)]
final class InheritanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    #[Test]
    public function inheritedClassAttributesIncludeParent(): void
    {
        $own = Attr::ofClass(InheritanceChild::class, inherited: false);
        $this->assertTrue($own->has(ClassMarker::class));

        $inherited = Attr::ofClass(InheritanceChild::class, inherited: true);
        // 子类自身无 ClassMarker('base')，继承链应上溯到基类
        $baseMarker = $inherited->get(ClassMarker::class);
        $this->assertNotNull($baseMarker);
        $this->assertEquals('child', $baseMarker->getArgument('label'));

        // 基类另有 label='base' 的 ClassMarker，应在继承合并后保留
        $allMarkers = $inherited->getAll(ClassMarker::class);
        $labels = $allMarkers->pluck('label');
        $this->assertContains('base', $labels);
        $this->assertContains('child', $labels);
    }

    #[Test]
    public function inheritedPrivateParentPropertyIsCollected(): void
    {
        // 'secret' 声明于基类且为 private；非继承模式找不到，继承模式应找到。
        $without = Attr::ofProperty(InheritanceChild::class, 'secret', inherited: false);
        $this->assertEquals(0, count($without), '非继承模式不应读到父类私有属性');

        $with = Attr::ofProperty(InheritanceChild::class, 'secret', inherited: true);
        $this->assertGreaterThan(0, count($with), '继承模式必须读到父类私有属性');
        $this->assertTrue($with->has(InjectAttribute::class));
        $this->assertEquals('base-secret', $with->first()->getArgument('service'));
    }

    #[Test]
    public function inheritedPropertyGroupIncludesParentPrivate(): void
    {
        $props = Attr::properties(InheritanceChild::class, attrClass: InjectAttribute::class, inherited: true);

        $this->assertArrayHasKey('childProp', $props);
        $this->assertArrayHasKey('secret', $props, '属性分组必须包含父类私有属性');

        $this->assertEquals('child-prop', $props['childProp']->first()->getArgument('service'));
        $this->assertEquals('base-secret', $props['secret']->first()->getArgument('service'));
    }

    #[Test]
    public function inheritedMethodAttributes(): void
    {
        $methods = Attr::methods(InheritanceChild::class, inherited: true);

        $this->assertArrayHasKey('baseMethod', $methods, '继承链上溯应发现基类 baseMethod 的属性');
        $this->assertTrue($methods['baseMethod']->has(MethodMarker::class));
        $this->assertEquals('base-method', $methods['baseMethod']->first()->getArgument('name'));
    }

    #[Test]
    public function collectPropertiesTraversesParents(): void
    {
        $reader = new Reader();
        $ref = new \ReflectionClass(InheritanceChild::class);

        $nonInherited = $reader->collectProperties($ref, inherited: false);
        $this->assertArrayNotHasKey('secret', $nonInherited);

        $inherited = $reader->collectProperties($ref, inherited: true);
        $this->assertArrayHasKey('secret', $inherited);
        $this->assertArrayHasKey('childProp', $inherited);
    }
}
