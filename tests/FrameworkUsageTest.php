<?php

declare(strict_types=1);

namespace Kode\Attributes\Tests;

use Kode\Attributes\Attr;
use Kode\Attributes\MetaList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures.php';

#[CoversClass(Attr::class)]
final class FrameworkUsageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Attr::clear();
    }

    #[Test]
    public function readsClassLevelRouteAttribute(): void
    {
        $list = Attr::ofClass(UserController::class);

        $this->assertInstanceOf(MetaList::class, $list);
        $this->assertTrue($list->has(RouteAttribute::class));

        $route = $list->first()->getInstance();
        $this->assertSame('/users', $route->path);
        $this->assertSame(['GET', 'POST'], $route->methods);
    }

    #[Test]
    public function readsMethodLevelRouteAttribute(): void
    {
        $list = Attr::ofMethod(UserController::class, 'show');

        $this->assertTrue($list->has(RouteAttribute::class));
        $this->assertSame('/users/{id}', $list->first()->getInstance()->path);
    }

    #[Test]
    public function readsPropertyInjectionAttribute(): void
    {
        // 框架 DI 容器常用：读取属性上的注入声明
        $list = Attr::ofProperty(UserController::class, 'repo');

        $this->assertTrue($list->has(InjectAttribute::class));
        $this->assertSame('App\\Service\\UserRepo', $list->first()->getInstance()->service);
    }

    #[Test]
    public function fluentOnApiReadsMethodAttribute(): void
    {
        // 链式读取：等价于框架路由收集器的内部逻辑
        $route = Attr::on([UserController::class, 'show'])
            ->get(RouteAttribute::class)
            ?->getInstance();

        $this->assertNotNull($route);
        $this->assertSame('/users/{id}', $route->path);
    }

    #[Test]
    public function reflectionPropertyReadsTargetNotReflectionSelf(): void
    {
        // 根因回归：直接传 ReflectionProperty 应读取控制器属性上的属性，而非 Reflection 类自身。
        $ref = new \ReflectionProperty(UserController::class, 'repo');
        $list = Attr::of($ref);

        $this->assertTrue($list->has(InjectAttribute::class));
        $this->assertSame('App\\Service\\UserRepo', $list->first()->getInstance()->service);
    }
}
