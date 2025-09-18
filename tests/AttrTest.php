<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Reader;
use PHPUnit\Framework\TestCase;

class AttrTest extends TestCase
{
    public function testAttrFacade(): void
    {
        // Test that the Attr facade can read class attributes
        $metaList = Attr::of(SampleClass::class);
        
        $this->assertGreaterThan(0, count($metaList));
        $this->assertTrue(Attr::has(SampleClass::class, SampleAttribute::class));
        
        $meta = Attr::get(SampleClass::class, SampleAttribute::class);
        $this->assertNotNull($meta);
        $this->assertEquals(SampleAttribute::class, $meta->name);
    }

    public function testReader(): void
    {
        $reader = new Reader();
        
        // Test class attributes
        $classList = $reader->getClassAttrs(SampleClass::class);
        $this->assertGreaterThan(0, count($classList));
        
        // Test method attributes
        $methodList = $reader->getMethodAttrs(SampleClass::class, 'sampleMethod');
        $this->assertGreaterThan(0, count($methodList));
        
        // Test that we can get attribute instances
        $meta = $classList->first();
        $this->assertNotNull($meta);
        
        $instance = $meta->getInstance();
        $this->assertInstanceOf(SampleAttribute::class, $instance);
    }

    public function testMetaList(): void
    {
        $metaList = Attr::of(SampleClass::class);
        
        // Test filtering
        $filtered = $metaList->filter(fn($meta) => $meta->name === SampleAttribute::class);
        $this->assertGreaterThan(0, count($filtered));
        
        // Test getting specific attribute
        $meta = $metaList->get(SampleAttribute::class);
        $this->assertNotNull($meta);
        
        // Test mapping
        $names = $metaList->map(fn($meta) => $meta->name);
        $this->assertContains(SampleAttribute::class, $names);
    }
}