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

#[CoversClass(TargetRef::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Attr::class)]
final class EnumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    #[Test]
    public function enumCaseConstantAttributes(): void
    {
        $list = Attr::ofConstant(RoleEnum::class, 'Admin');

        $this->assertGreaterThan(0, count($list));
        $this->assertTrue($list->has(ConstMarker::class));
        $this->assertEquals('admin-role', $list->first()->getArgument('role'));
    }

    #[Test]
    public function enumCaseDirectAccess(): void
    {
        $list = Attr::ofEnumCase(RoleEnum::Admin);

        $this->assertGreaterThan(0, count($list));
        $this->assertTrue($list->has(ConstMarker::class));
        $this->assertEquals('admin-role', $list->first()->getArgument('role'));
    }

    #[Test]
    public function enumAllConstants(): void
    {
        $constants = Attr::constants(RoleEnum::class, attrClass: ConstMarker::class);

        $this->assertArrayHasKey('Admin', $constants);
        $this->assertArrayHasKey('Guest', $constants);
        $this->assertArrayHasKey('User', $constants);
    }

    #[Test]
    public function targetRefConstantResolvesEnumUnitCase(): void
    {
        $ref = TargetRef::constant(RoleEnum::class, 'Guest');

        $this->assertInstanceOf(\ReflectionEnumUnitCase::class, $ref);
        $this->assertEquals('Guest', $ref->getName());
    }

    #[Test]
    public function missingEnumCaseThrows(): void
    {
        $this->expectException(\Kode\Attributes\Exception\TargetNotFoundException::class);

        Attr::ofConstant(RoleEnum::class, 'NonExistentCase');
    }
}
