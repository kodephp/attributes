<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\Reader;
use Kode\Attributes\TargetRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';
require_once __DIR__ . '/Fixtures/GlobalFn.php';

#[CoversClass(TargetRef::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Attr::class)]
final class FunctionTargetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    #[Test]
    public function namedGlobalFunctionAttributes(): void
    {
        $list = Attr::ofFunction('kode_attributes_global_fn');

        $this->assertGreaterThan(0, count($list));
        $this->assertTrue($list->has(\KodeFnMarker::class));
        $this->assertEquals('global-fn', $list->first()->getArgument('label'));
    }

    #[Test]
    public function closureFunctionAttributes(): void
    {
        $fn = function () {};

        $list = Attr::ofFunction($fn);
        $this->assertInstanceOf(\Kode\Attributes\MetaList::class, $list);
    }

    #[Test]
    public function ofAcceptsFunctionNameString(): void
    {
        $list = Attr::of('kode_attributes_global_fn');

        $this->assertGreaterThan(0, count($list));
        $this->assertTrue($list->has(\KodeFnMarker::class));
    }

    #[Test]
    public function targetRefFunctionResolvesReflectionFunction(): void
    {
        $ref = TargetRef::function('kode_attributes_global_fn');
        $this->assertInstanceOf(\ReflectionFunction::class, $ref);
        $this->assertEquals('kode_attributes_global_fn', $ref->getName());
    }

    #[Test]
    public function missingFunctionThrows(): void
    {
        $this->expectException(\Kode\Attributes\Exception\TargetNotFoundException::class);

        Attr::ofFunction('kode_attributes_this_function_does_not_exist');
    }
}
