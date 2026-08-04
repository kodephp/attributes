<?php

/*
 * This file is part of the kode/attributes package.
 *
 * (c) kode (KodePHP) <382601296@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Kode\Attributes;

use Closure;
use Kode\Attributes\Cache\ArrayCache;
use Kode\Attributes\CacheInterface;
use Kode\Attributes\Exception\AttributeInstantiationException;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use UnitEnum;

/**
 * 属性读取器全局门面类。
 *
 * 提供统一的静态访问入口，简化属性操作。内部持有单例 Reader，避免重复创建。
 *
 * ## 2.0 重要修复
 *
 * 1.x 的 `of()/has()/get()` 只接受"类名字符串或对象"。由于 `ReflectionClass`、
 * `ReflectionProperty`、`ReflectionParameter` 本身也是对象，传入它们会被当作
 * 普通对象处理，转而读取 **Reflection 类自身** 的属性——永远返回空集合，
 * 且不抛任何异常。这正是"属性注入整条链路静默失效"的根因。
 *
 * 2.0 起所有入口接受任意 Reflector 实例、闭包、可调用数组，以及
 * `Foo::method` / `Foo::$prop` / `Foo::CONST` / `Foo::method($arg)` 等字符串写法；
 * 目标不存在时抛出 {@see Exception\TargetNotFoundException} 而不是静默返回空集合。
 *
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final class Attr
{
    /**
     * 组件版本号。
     */
    public const string VERSION = '2.1.1';

    /**
     * Reader 单例实例。
     */
    private static ?Reader $reader = null;

    /**
     * 私有构造函数，防止实例化。
     */
    private function __construct()
    {
    }

    /**
     * 获取 Reader 实例（单例模式）。
     */
    public static function reader(): Reader
    {
        return self::$reader ??= new Reader();
    }

    /**
     * 设置自定义 Reader 实例。
     *
     * @param Reader $reader 自定义 Reader 实例
     */
    public static function setReader(Reader $reader): void
    {
        self::$reader = $reader;
    }

    /**
     * 设置全局缓存实现（用于多进程 / 分布式共享）。
     *
     * 例如注入 {@see \Kode\Attributes\Cache\RedisCache} 即可让所有 worker、
     * 所有节点共享反射元数据，避免重复反射；或注入其它实现了
     * {@see CacheInterface} 的驱动（APCu、文件、内存等）。
     *
     * @param CacheInterface $cache 缓存实现
     */
    public static function setCache(CacheInterface $cache): void
    {
        self::$reader = self::reader()->withCache($cache);
    }

    /**
     * 开启严格模式（属性构造异常时抛出而非静默跳过）。
     *
     * @param bool $strict 是否严格
     */
    public static function strict(bool $strict = true): void
    {
        self::$reader = self::reader()->withStrict($strict);
    }

    /**
     * 创建目录扫描器实例。
     *
     * @param string|null $dir 默认扫描目录，可在调用 scan()/find() 时省略
     */
    public static function scan(?string $dir = null): Scanner
    {
        return new Scanner(self::reader(), $dir);
    }

    /**
     * 针对某个目标创建链式检查器。
     *
     * @param mixed $target 目标
     */
    public static function on(mixed $target): Inspector
    {
        return new Inspector(self::reader(), $target);
    }

    /**
     * 获取目标的所有属性元数据。
     *
     * @param mixed $target 目标：类名、对象、Reflector、Closure、可调用数组或成员字符串
     * @param bool $inherited 是否沿继承链读取
     */
    public static function of(mixed $target, bool $inherited = false): MetaList
    {
        return self::reader()->read($target, $inherited);
    }

    /**
     * 检查目标是否具有指定属性。
     *
     * @param mixed $target 目标
     * @param string $attrClass 属性类名
     * @param bool $inherited 是否沿继承链读取
     */
    public static function has(mixed $target, string $attrClass, bool $inherited = false): bool
    {
        return self::of($target, $inherited)->has($attrClass);
    }

    /**
     * 获取目标的指定属性元数据。
     *
     * @param mixed $target 目标
     * @param string $attrClass 属性类名
     * @param bool $inherited 是否沿继承链读取
     */
    public static function get(mixed $target, string $attrClass, bool $inherited = false): ?Meta
    {
        return self::of($target, $inherited)->get($attrClass);
    }

    /**
     * 获取目标的所有指定类型属性元数据。
     *
     * @param mixed $target 目标
     * @param string $attrClass 属性类名
     * @param bool $inherited 是否沿继承链读取
     */
    public static function getAll(mixed $target, string $attrClass, bool $inherited = false): MetaList
    {
        return self::of($target, $inherited)->getAll($attrClass);
    }

    /**
     * 直接获取属性实例。
     *
     * @param mixed $target 目标
     * @param string $attrClass 属性类名
     * @param bool $inherited 是否沿继承链读取
     */
    public static function instance(mixed $target, string $attrClass, bool $inherited = false): ?object
    {
        return self::get($target, $attrClass, $inherited)?->getInstance();
    }

    /**
     * 直接获取全部属性实例。
     *
     * @param mixed $target 目标
     * @param string $attrClass 属性类名
     * @param bool $inherited 是否沿继承链读取
     * @return array<int, object> 属性实例数组
     */
    public static function instances(mixed $target, string $attrClass, bool $inherited = false): array
    {
        $list = self::getAll($target, $attrClass, $inherited);

        // 严格模式：实例化失败直接抛出；非严格：静默跳过损坏的属性。
        return self::reader()->isStrict() ? $list->getInstances() : $list->safeInstances();
    }

    /**
     * 获取类级属性。
     *
     * @param object|string $class 类名或对象
     * @param bool $inherited 是否沿继承链读取
     */
    public static function ofClass(object|string $class, bool $inherited = false): MetaList
    {
        return self::reader()->getClassAttrs($class, $inherited);
    }

    /**
     * 获取指定方法的属性。
     */
    public static function ofMethod(object|string $class, string $method, bool $inherited = false): MetaList
    {
        return self::reader()->getMethodAttrs($class, $method, $inherited);
    }

    /**
     * 获取指定属性（property）的属性。
     */
    public static function ofProperty(object|string $class, string $property, bool $inherited = false): MetaList
    {
        return self::reader()->getPropertyAttrs($class, $property, $inherited);
    }

    /**
     * 获取指定类常量（含枚举 case）的属性。
     */
    public static function ofConstant(object|string $class, string $constant): MetaList
    {
        return self::reader()->getConstantAttrs($class, $constant);
    }

    /**
     * 获取函数的属性。
     */
    public static function ofFunction(string|Closure $function): MetaList
    {
        return self::reader()->getFunctionAttrs($function);
    }

    /**
     * 获取参数的属性。
     *
     * @param ReflectionParameter|string|array{0: object|string, 1: string}|Closure $target 参数反射或其所属函数/方法
     * @param string|int|null $parameter 参数名或位置
     */
    public static function ofParameter(
        ReflectionParameter|string|array|Closure $target,
        string|int|null $parameter = null
    ): MetaList {
        return self::reader()->getParameterAttrs($target, $parameter);
    }

    /**
     * 获取枚举 case 的属性。
     */
    public static function ofEnumCase(UnitEnum $case): MetaList
    {
        return self::reader()->read(TargetRef::enumCase($case));
    }

    /**
     * 获取类中所有带属性的方法。
     *
     * @param object|string $class 类名或对象
     * @param string|null $attrClass 仅保留含该属性的方法
     * @param bool $inherited 是否沿继承链读取
     * @return array<string, MetaList> 方法名 => 属性集合
     */
    public static function methods(object|string $class, ?string $attrClass = null, bool $inherited = false): array
    {
        return self::filterByAttribute(self::reader()->getAllMethodAttrs($class, $inherited), $attrClass);
    }

    /**
     * 获取类中所有带属性的属性（property）。
     *
     * 属性注入最常用的入口。`$inherited = true` 时会一并收集
     * 父类的 private 属性——这是继承结构下注入"部分失效"的常见原因。
     *
     * @param object|string $class 类名或对象
     * @param string|null $attrClass 仅保留含该属性的成员
     * @param bool $inherited 是否包含父类（含私有）属性
     * @return array<string, MetaList> 属性名 => 属性集合
     */
    public static function properties(object|string $class, ?string $attrClass = null, bool $inherited = false): array
    {
        return self::filterByAttribute(self::reader()->getAllPropertyAttrs($class, $inherited), $attrClass);
    }

    /**
     * 获取类中所有带属性的常量（含枚举 case）。
     *
     * @param object|string $class 类名或对象
     * @param string|null $attrClass 仅保留含该属性的常量
     * @return array<string, MetaList> 常量名 => 属性集合
     */
    public static function constants(object|string $class, ?string $attrClass = null): array
    {
        return self::filterByAttribute(self::reader()->getAllConstantAttrs($class), $attrClass);
    }

    /**
     * 获取函数/方法所有带属性的参数。
     *
     * @param object|string $target 类名、对象、闭包、函数名或函数反射
     *                              （ReflectionFunctionAbstract 也是 object，故归并到 object）
     * @param string|null $method 当 $target 为类/对象时的方法名
     * @param string|null $attrClass 仅保留含该属性的参数
     * @return array<string, MetaList> 参数名 => 属性集合
     */
    public static function parameters(
        object|string $target,
        ?string $method = null,
        ?string $attrClass = null
    ): array {
        return self::filterByAttribute(self::reader()->getAllParameterAttrs($target, $method), $attrClass);
    }

    /**
     * 重置门面：清空缓存并丢弃单例 Reader。
     */
    public static function clear(): void
    {
        self::$reader?->clearCache();
        self::$reader = null;
        Meta::clearStaticCache();
    }

    /**
     * 仅清空当前 Reader 的缓存，保留 Reader 实例与配置。
     */
    public static function clearCache(): void
    {
        self::reader()->clearCache();
    }

    /**
     * 按属性类名过滤"成员名 => MetaList"映射。
     *
     * @param array<string, MetaList> $groups 成员映射
     * @param string|null $attrClass 属性类名
     * @return array<string, MetaList> 过滤后的映射
     */
    private static function filterByAttribute(array $groups, ?string $attrClass): array
    {
        if ($attrClass === null) {
            return $groups;
        }

        $result = [];

        foreach ($groups as $name => $metas) {
            $filtered = $metas->getAll($attrClass);

            if ($filtered->isNotEmpty()) {
                $result[$name] = $filtered;
            }
        }

        return $result;
    }
}
