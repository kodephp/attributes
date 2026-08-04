<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Attribute;
use Kode\Attributes\Attr;
use Kode\Attributes\Reader;
use Kode\Attributes\Meta;
use Kode\Attributes\MetaList;
use Kode\Attributes\Target;
use Kode\Attributes\Flags;
use Kode\Attributes\ArrayCache;
use Kode\Attributes\Scanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(Attr::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Meta::class)]
#[CoversClass(MetaList::class)]
#[CoversClass(Target::class)]
#[CoversClass(Flags::class)]
#[CoversClass(ArrayCache::class)]
#[CoversClass(Scanner::class)]
final class AttrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    #[Test]
    public function versionIsTwo(): void
    {
        $this->assertSame('2.0.0', Attr::VERSION);
    }

    #[Test]
    public function attrFacadeBasicUsage(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $this->assertInstanceOf(MetaList::class, $metaList);
        $this->assertGreaterThan(0, count($metaList));
        $this->assertTrue(Attr::has(SampleClass::class, SampleAttribute::class));

        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);
        $this->assertEquals(SampleAttribute::class, $meta->name);
    }

    #[Test]
    public function attrFacadeGetAll(): void
    {
        $allMetas = Attr::getAll(SampleClass::class, SampleAttribute::class);

        $this->assertInstanceOf(MetaList::class, $allMetas);
        $this->assertEquals(2, count($allMetas));
    }

    #[Test]
    public function attrFacadeWithObject(): void
    {
        $object = new SampleClass();
        $metaList = Attr::of($object);

        $this->assertInstanceOf(MetaList::class, $metaList);
        $this->assertTrue($metaList->has(SampleAttribute::class));
    }

    #[Test]
    public function attrInstanceAndInstances(): void
    {
        $instance = Attr::instance(SampleClass::class, SampleAttribute::class);
        $this->assertInstanceOf(SampleAttribute::class, $instance);
        $this->assertEquals('test-class', $instance->name);

        $instances = Attr::instances(SampleClass::class, SampleAttribute::class);
        $this->assertCount(2, $instances);
        $this->assertContainsOnlyInstancesOf(SampleAttribute::class, $instances);
    }

    #[Test]
    public function attrScopedAccessors(): void
    {
        $this->assertGreaterThan(0, count(Attr::ofClass(SampleClass::class)));
        $this->assertGreaterThan(0, count(Attr::ofMethod(SampleClass::class, 'sampleMethod')));
        $this->assertGreaterThan(0, count(Attr::ofProperty(SampleClass::class, 'property')));
        $this->assertGreaterThan(0, count(Attr::ofConstant(SampleClassWithConstant::class, 'SAMPLE_CONSTANT')));
    }

    #[Test]
    public function attrGroupAccessors(): void
    {
        $methods = Attr::methods(SampleClass::class);
        $this->assertArrayHasKey('sampleMethod', $methods);

        $properties = Attr::properties(SampleClass::class);
        $this->assertArrayHasKey('property', $properties);

        $constants = Attr::constants(SampleClassWithConstant::class);
        $this->assertArrayHasKey('SAMPLE_CONSTANT', $constants);

        // 仅保留含指定属性的成员
        $onlySample = Attr::methods(SampleClass::class, SampleAttribute::class);
        $this->assertArrayHasKey('sampleMethod', $onlySample);
    }

    #[Test]
    public function readerClassAttributes(): void
    {
        $reader = new Reader();

        $classList = $reader->getClassAttrs(SampleClass::class);
        $this->assertGreaterThan(0, count($classList));

        $meta = $classList->first();
        $this->assertNotNull($meta);
        $this->assertEquals(SampleAttribute::class, $meta->name);
    }

    #[Test]
    public function readerMethodAttributes(): void
    {
        $reader = new Reader();

        $methodList = $reader->getMethodAttrs(SampleClass::class, 'sampleMethod');
        $this->assertGreaterThan(0, count($methodList));

        $meta = $methodList->first();
        $this->assertNotNull($meta);
        $instance = $meta->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instance);
        $this->assertEquals('test-method', $instance->name);
        $this->assertEquals(5, $instance->priority);
    }

    #[Test]
    public function readerPropertyAttributes(): void
    {
        $reader = new Reader();

        $propertyList = $reader->getPropertyAttrs(SampleClass::class, 'property');
        $this->assertGreaterThan(0, count($propertyList));

        $meta = $propertyList->first();
        $this->assertNotNull($meta);
        $instance = $meta->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instance);
        $this->assertEquals('test-property', $instance->name);
    }

    #[Test]
    public function readerConstantAttributes(): void
    {
        $reader = new Reader();

        $constAttrs = $reader->getConstantAttrs(SampleClassWithConstant::class, 'SAMPLE_CONSTANT');
        $this->assertInstanceOf(MetaList::class, $constAttrs);
        $this->assertGreaterThan(0, count($constAttrs));
    }

    #[Test]
    public function readerAllMethodsAndProperties(): void
    {
        $reader = new Reader();

        $allMethods = $reader->getAllMethodAttrs(SampleClass::class);
        $this->assertArrayHasKey('sampleMethod', $allMethods);

        $allProperties = $reader->getAllPropertyAttrs(SampleClass::class);
        $this->assertArrayHasKey('property', $allProperties);
    }

    #[Test]
    public function metaGetInstance(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);

        $instance = $meta->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instance);
        $this->assertEquals('test-class', $instance->name);
        $this->assertEquals(10, $instance->priority);
    }

    #[Test]
    public function metaNewInstance(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);

        $instance = $meta->newInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instance);
    }

    #[Test]
    public function metaIsRepeatable(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);
        $this->assertTrue($meta->isRepeatable());
    }

    #[Test]
    public function metaGetTarget(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);

        $target = $meta->getTarget();
        $this->assertInstanceOf(Target::class, $target);
    }

    #[Test]
    public function metaSupportsTarget(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);

        $this->assertTrue($meta->supportsTarget(Target::Clazz));
        $this->assertTrue($meta->supportsTarget(Target::Method));
    }

    #[Test]
    public function metaArguments(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);

        $instance = $meta->getInstance();
        $this->assertEquals('test-class', $instance->name);
        $this->assertEquals(10, $instance->priority);

        $this->assertEquals('default', $meta->getArgument('nonexistent', 'default'));

        $args = $meta->args;
        $this->assertIsArray($args);
    }

    #[Test]
    public function metaGetDeclaringClass(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);
        $this->assertEquals(SampleClass::class, $meta->getDeclaringClass());
    }

    #[Test]
    public function metaToArray(): void
    {
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);

        $array = $meta->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('args', $array);
        $this->assertArrayHasKey('target', $array);
        $this->assertArrayHasKey('targets', $array);
    }

    #[Test]
    public function metaListFirst(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $first = $metaList->first();
        $this->assertInstanceOf(Meta::class, $first);
    }

    #[Test]
    public function metaListLast(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $last = $metaList->last();
        $this->assertInstanceOf(Meta::class, $last);
    }

    #[Test]
    public function metaListFilter(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $filtered = $metaList->filter(fn($meta) => $meta->name === SampleAttribute::class);
        $this->assertGreaterThan(0, count($filtered));

        $filtered->each(function ($meta) {
            $this->assertEquals(SampleAttribute::class, $meta->name);
        });
    }

    #[Test]
    public function metaListMap(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $names = $metaList->map(fn($meta) => $meta->name);
        $this->assertContains(SampleAttribute::class, $names);
    }

    #[Test]
    public function metaListFlatMap(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $result = $metaList->flatMap(fn($meta) => [$meta->name]);
        $this->assertContains(SampleAttribute::class, $result);
    }

    #[Test]
    public function metaListHas(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $this->assertTrue($metaList->has(SampleAttribute::class));
        $this->assertFalse($metaList->has('NonExistentAttribute'));
    }

    #[Test]
    public function metaListGet(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $meta = $metaList->get(SampleAttribute::class);
        $this->assertInstanceOf(Meta::class, $meta);

        $nullMeta = $metaList->get('NonExistentAttribute');
        $this->assertNull($nullMeta);
    }

    #[Test]
    public function metaListGetAll(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $all = $metaList->getAll(SampleAttribute::class);
        $this->assertEquals(2, count($all));
    }

    #[Test]
    public function metaListMerge(): void
    {
        $list1 = new MetaList([Attr::get(SampleClass::class, SampleAttribute::class)]);
        $list2 = new MetaList([]);

        $merged = $list1->merge($list2);
        $this->assertEquals(1, count($merged));
    }

    #[Test]
    public function metaListIsEmpty(): void
    {
        $emptyList = new MetaList([]);
        $this->assertTrue($emptyList->isEmpty());
        $this->assertFalse($emptyList->isNotEmpty());

        $metaList = Attr::of(SampleClass::class);
        $this->assertFalse($metaList->isEmpty());
        $this->assertTrue($metaList->isNotEmpty());
    }

    #[Test]
    public function metaListSomeAndEvery(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $this->assertTrue($metaList->some(fn($meta) => $meta->name === SampleAttribute::class));
        $this->assertFalse($metaList->some(fn($meta) => $meta->name === 'NonExistent'));

        $this->assertTrue($metaList->every(fn($meta) => $meta instanceof Meta));
    }

    #[Test]
    public function metaListAt(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $meta = $metaList->at(0);
        $this->assertInstanceOf(Meta::class, $meta);

        $nullMeta = $metaList->at(999);
        $this->assertNull($nullMeta);
    }

    #[Test]
    public function metaListGetInstances(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $instances = $metaList->getInstances();
        $this->assertIsArray($instances);
        $this->assertGreaterThan(0, count($instances));
    }

    #[Test]
    public function metaListGroupByName(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $groups = $metaList->groupByName();
        $this->assertArrayHasKey(SampleAttribute::class, $groups);
        $this->assertInstanceOf(MetaList::class, $groups[SampleAttribute::class]);
    }

    #[Test]
    public function metaListGroupBy(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $groups = $metaList->groupBy(fn($meta) => $meta->name === SampleAttribute::class ? 'sample' : 'other');
        $this->assertArrayHasKey('sample', $groups);
    }

    #[Test]
    public function metaListSort(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $sorted = $metaList->sort(fn($a, $b) => strcmp($a->name, $b->name));
        $this->assertInstanceOf(MetaList::class, $sorted);
    }

    #[Test]
    public function metaListReverse(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $reversed = $metaList->reverse();
        $this->assertInstanceOf(MetaList::class, $reversed);
    }

    #[Test]
    public function metaListTakeAndSkip(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $taken = $metaList->take(1);
        $this->assertEquals(1, count($taken));

        $skipped = $metaList->skip(1);
        $this->assertLessThan(count($metaList), count($skipped));
    }

    #[Test]
    public function metaListToArray(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $array = $metaList->toArray();
        $this->assertIsArray($array);
    }

    #[Test]
    public function metaListIterator(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $count = 0;
        foreach ($metaList as $meta) {
            $this->assertInstanceOf(Meta::class, $meta);
            $count++;
        }
        $this->assertGreaterThan(0, $count);
    }

    #[Test]
    public function arrayCacheBasic(): void
    {
        $cache = new ArrayCache();

        $callCount = 0;
        $value = $cache->get('test_key', function () use (&$callCount) {
            $callCount++;
            return 'test_value';
        });

        $this->assertEquals('test_value', $value);
        $this->assertEquals(1, $callCount);

        $cache->get('test_key', function () use (&$callCount) {
            $callCount++;
            return 'new_value';
        });

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function arrayCacheHas(): void
    {
        $cache = new ArrayCache();

        $this->assertFalse($cache->has('test_key'));

        $cache->set('test_key', 'value');
        $this->assertTrue($cache->has('test_key'));
    }

    #[Test]
    public function arrayCacheDelete(): void
    {
        $cache = new ArrayCache();

        $cache->set('test_key', 'value');
        $this->assertTrue($cache->has('test_key'));

        $cache->delete('test_key');
        $this->assertFalse($cache->has('test_key'));
    }

    #[Test]
    public function arrayCacheClear(): void
    {
        $cache = new ArrayCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $cache->clear();

        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    #[Test]
    public function arrayCacheStats(): void
    {
        $cache = new ArrayCache();

        $cache->get('key1', fn() => 'value1');
        $cache->get('key1', fn() => 'value1');
        $cache->get('key2', fn() => 'value2');

        $stats = $cache->getStats();

        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(2, $stats['misses']);
        $this->assertEquals(2, $stats['size']);
        $this->assertEquals(ArrayCache::DEFAULT_CAPACITY, $stats['capacity']);
    }

    #[Test]
    public function arrayCacheLruEviction(): void
    {
        $cache = new ArrayCache(2);

        $cache->get('a', fn() => 1);
        $cache->get('b', fn() => 2);
        $this->assertEquals(2, $cache->getSize());

        // 第三次写入触发 LRU 淘汰
        $cache->get('c', fn() => 3);
        $this->assertEquals(2, $cache->getSize());
        $this->assertEquals(1, $cache->getStats()['evictions']);

        // 被淘汰的 'a' 需要重新加载
        $cache->get('a', fn() => 11);
        $this->assertEquals(2, $cache->getSize());
    }

    #[Test]
    public function arrayCacheDeleteByPrefix(): void
    {
        $cache = new ArrayCache();

        $cache->set('ns:one', 1);
        $cache->set('ns:two', 2);
        $cache->set('other', 3);

        $deleted = $cache->deleteByPrefix('ns:');
        $this->assertEquals(2, $deleted);
        $this->assertFalse($cache->has('ns:one'));
        $this->assertTrue($cache->has('other'));
    }

    #[Test]
    public function arrayCacheGetKeys(): void
    {
        $cache = new ArrayCache();

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $keys = $cache->getKeys();

        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
    }

    #[Test]
    public function arrayCacheHitRatePercent(): void
    {
        $cache = new ArrayCache();

        $cache->get('key1', fn() => 'value1');
        $cache->get('key1', fn() => 'value1');

        $this->assertEquals(50.0, $cache->getHitRatePercent());
    }

    #[Test]
    public function targetEnum(): void
    {
        $this->assertEquals(1, Target::Clazz->value);
        $this->assertEquals(2, Target::Function->value);
        $this->assertEquals(4, Target::Method->value);
        $this->assertEquals(8, Target::Property->value);
        $this->assertEquals(16, Target::ClassConstant->value);
        $this->assertEquals(32, Target::Parameter->value);
        $this->assertEquals(63, Target::All->value);
    }

    #[Test]
    public function targetFromRef(): void
    {
        $classRef = new \ReflectionClass(SampleClass::class);
        $this->assertEquals(Target::Clazz, Target::fromRef($classRef));

        $methodRef = new \ReflectionMethod(SampleClass::class, 'sampleMethod');
        $this->assertEquals(Target::Method, Target::fromRef($methodRef));

        $propertyRef = new \ReflectionProperty(SampleClass::class, 'property');
        $this->assertEquals(Target::Property, Target::fromRef($propertyRef));
    }

    #[Test]
    public function targetFromAttributeFlags(): void
    {
        $target = Target::fromAttributeFlags(Attribute::TARGET_CLASS);
        $this->assertEquals(Target::Clazz, $target);

        $target = Target::fromAttributeFlags(Attribute::TARGET_ALL);
        $this->assertEquals(Target::All, $target);
    }

    #[Test]
    public function targetSupports(): void
    {
        $this->assertTrue(Target::All->supports(Target::Clazz));
        $this->assertTrue(Target::Clazz->supports(Target::Clazz));
        $this->assertFalse(Target::Clazz->supports(Target::Method));
    }

    #[Test]
    public function targetCombine(): void
    {
        $combined = Target::Clazz->combine(Target::Method);
        $this->assertTrue($combined->supports(Target::Clazz));
        $this->assertTrue($combined->supports(Target::Method));
    }

    #[Test]
    public function targetGetTargets(): void
    {
        $targets = Target::Clazz->getTargets();
        $this->assertCount(1, $targets);
        $this->assertContains(Target::Clazz, $targets);

        $allTargets = Target::All->getTargets();
        $this->assertCount(6, $allTargets);
    }

    #[Test]
    public function targetToAttributeFlags(): void
    {
        $flags = Target::Clazz->toAttributeFlags();
        $this->assertEquals(Attribute::TARGET_CLASS, $flags);
    }

    #[Test]
    public function targetGetLabel(): void
    {
        $this->assertEquals('类', Target::Clazz->getLabel());
        $this->assertEquals('方法', Target::Method->getLabel());
        $this->assertEquals('全部', Target::All->getLabel());
    }

    #[Test]
    public function targetToString(): void
    {
        $this->assertEquals('Clazz', Target::Clazz->toString());
        $this->assertStringContainsString('Clazz', Target::All->toString());
    }

    #[Test]
    public function flagsBasic(): void
    {
        $flags = new Flags(inherit: true, priority: 100);

        $this->assertTrue($flags->inherit);
        $this->assertFalse($flags->compileTime);
        $this->assertEquals(100, $flags->priority);
        $this->assertTrue($flags->cacheable);
    }

    #[Test]
    public function flagsIsDefault(): void
    {
        $defaultFlags = new Flags();
        $this->assertTrue($defaultFlags->isDefault());

        $customFlags = new Flags(inherit: true);
        $this->assertFalse($customFlags->isDefault());
    }

    #[Test]
    public function flagsMerge(): void
    {
        $flags1 = new Flags(inherit: true, priority: 50);
        $flags2 = new Flags(compileTime: true, priority: 100);

        $merged = $flags1->merge($flags2);

        $this->assertTrue($merged->inherit);
        $this->assertTrue($merged->compileTime);
        $this->assertEquals(100, $merged->priority);
    }

    #[Test]
    public function flagsStaticFactories(): void
    {
        $inherit = Flags::inherit(50);
        $this->assertTrue($inherit->inherit);
        $this->assertEquals(50, $inherit->priority);

        $compileTime = Flags::compileTime(100);
        $this->assertTrue($compileTime->compileTime);

        $highPriority = Flags::highPriority(200);
        $this->assertEquals(200, $highPriority->priority);

        $nonCacheable = Flags::nonCacheable();
        $this->assertFalse($nonCacheable->cacheable);
    }

    #[Test]
    public function flagsToArray(): void
    {
        $flags = new Flags(inherit: true, priority: 100);
        $array = $flags->toArray();

        $this->assertTrue($array['inherit']);
        $this->assertFalse($array['compileTime']);
        $this->assertEquals(100, $array['priority']);
        $this->assertTrue($array['cacheable']);
    }

    #[Test]
    public function flagsFromArray(): void
    {
        $flags = Flags::fromArray(['inherit' => true, 'priority' => 100]);

        $this->assertTrue($flags->inherit);
        $this->assertEquals(100, $flags->priority);
    }

    #[Test]
    public function flagsToString(): void
    {
        $flags = new Flags(inherit: true, priority: 100);
        $string = (string) $flags;

        $this->assertStringContainsString('继承', $string);
        $this->assertStringContainsString('优先级:100', $string);
    }

    #[Test]
    public function readerWithCustomCache(): void
    {
        $cache = new ArrayCache();
        $reader = new Reader($cache);

        $reader->getClassAttrs(SampleClass::class);
        $reader->getClassAttrs(SampleClass::class);

        $stats = $cache->getStats();
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
    }

    #[Test]
    public function readerClearCache(): void
    {
        $cache = new ArrayCache();
        $reader = new Reader($cache);

        $reader->getClassAttrs(SampleClass::class);
        $this->assertEquals(1, $cache->getSize());

        $reader->clearCache();
        $this->assertEquals(0, $cache->getSize());
    }

    #[Test]
    public function attrSetReader(): void
    {
        $customReader = new Reader();
        Attr::setReader($customReader);

        $this->assertSame($customReader, Attr::reader());
    }

    #[Test]
    public function attrClear(): void
    {
        $reader = Attr::reader();
        $this->assertInstanceOf(Reader::class, $reader);

        Attr::clear();

        $newReader = Attr::reader();
        $this->assertNotSame($reader, $newReader);
    }

    #[Test]
    public function attrClearCache(): void
    {
        Attr::of(SampleClass::class);
        Attr::clearCache();

        $this->assertInstanceOf(Reader::class, Attr::reader());
    }

    #[Test]
    public function scannerScan(): void
    {
        $reader = new Reader();
        $scanner = new Scanner($reader);

        $scanner->exclude('vendor', '.git');

        $found = false;
        foreach ($scanner->scan(__DIR__) as $class => $metas) {
            if ($class === SampleClass::class) {
                $found = true;
                $this->assertTrue($metas->has(SampleAttribute::class));
                break;
            }
        }

        $this->assertTrue($found, 'SampleClass should be found by scanner');
    }

    #[Test]
    public function scannerFind(): void
    {
        $reader = new Reader();
        $scanner = new Scanner($reader);

        $scanner->exclude('vendor', '.git');

        $found = false;
        foreach ($scanner->find(__DIR__, SampleAttribute::class) as $class => $metas) {
            if ($class === SampleClass::class) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'SampleClass should be found by scanner with SampleAttribute');
    }

    #[Test]
    public function scannerScanDeep(): void
    {
        $reader = new Reader();
        $scanner = new Scanner($reader);

        $scanner->exclude('vendor', '.git');

        $found = false;
        foreach ($scanner->scanDeep(__DIR__) as $class => $info) {
            if ($class === SampleClass::class) {
                $found = true;
                $this->assertArrayHasKey('class', $info);
                $this->assertArrayHasKey('methods', $info);
                $this->assertArrayHasKey('properties', $info);
                $this->assertArrayHasKey('constants', $info);
                break;
            }
        }

        $this->assertTrue($found, 'SampleClass should be found by deep scanner');
    }

    #[Test]
    public function scannerClassesAndSkipped(): void
    {
        $reader = new Reader();
        $scanner = new Scanner($reader);

        $scanner->exclude('vendor', '.git');

        $classes = $scanner->classes(__DIR__);
        $this->assertContains(SampleClass::class, $classes);
        // 枚举也应被解析出来
        $this->assertContains(RoleEnum::class, $classes);
    }

    #[Test]
    public function metaListCountable(): void
    {
        $metaList = Attr::of(SampleClass::class);

        $this->assertGreaterThan(0, count($metaList));
    }

    #[Test]
    public function nonExistentAttribute(): void
    {
        $meta = Attr::get(SampleClass::class, 'NonExistentAttribute');
        $this->assertNull($meta);

        $this->assertFalse(Attr::has(SampleClass::class, 'NonExistentAttribute'));
    }

    #[Test]
    public function emptyMetaList(): void
    {
        $emptyList = new MetaList([]);

        $this->assertNull($emptyList->first());
        $this->assertNull($emptyList->last());
        $this->assertEquals(0, count($emptyList));
        $this->assertTrue($emptyList->isEmpty());
    }
}
