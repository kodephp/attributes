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
use Kode\Attributes\Exception\InvalidTargetException;
use Kode\Attributes\Exception\TargetNotFoundException;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionEnumUnitCase;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;
use UnitEnum;

/**
 * 目标解析器。
 *
 * 1.x 的致命缺陷：`Attr::of()` / `Reader::getAttributes()` 只接受"类名字符串或对象"。
 * 一旦传入 `ReflectionClass` / `ReflectionProperty` / `ReflectionParameter`，
 * 由于它们本身也是"对象"，代码会走 `$object::class` 分支，
 * 转而去读取 **Reflection 类自身** 的属性——结果永远是空集合，
 * 既不报错也无任何提示，整条属性注入链就此静默失效。
 *
 * 本类把所有形态的目标统一归一化为 `Reflector`，并为其生成防碰撞的缓存键：
 *
 * - `Reflector` 实例：原样返回（核心修复点）
 * - `Closure`：`ReflectionFunction`
 * - 任意对象：`ReflectionObject`
 * - `[$objOrClass, 'method']`：`ReflectionMethod`
 * - `'Foo\Bar'`：类 / 接口 / 特性 / 枚举
 * - `'Foo\Bar::method'`、`'Foo\Bar::method()'`：方法
 * - `'Foo\Bar::$prop'`：属性
 * - `'Foo\Bar::CONST'`：类常量（枚举则为枚举 case）
 * - `'Foo\Bar::method($arg)'`：方法参数
 * - `'strlen'`、`'strlen()'`：函数
 *
 * 解析失败时抛出异常而非返回空集合，杜绝"无声失效"。
 *
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final class TargetRef
{
    /**
     * 匿名类名中的标记片段。
     */
    public const string ANONYMOUS_MARK = '@anonymous';

    /**
     * 纯静态工具类，禁止实例化。
     */
    private function __construct()
    {
    }

    /**
     * 将任意目标归一化为 Reflector 实例。
     *
     * @param mixed $target 目标
     * @return Reflector 反射实例
     * @throws InvalidTargetException 目标类型不受支持
     * @throws TargetNotFoundException 目标语法合法但实际不存在
     */
    public static function resolve(mixed $target): Reflector
    {
        // 核心修复：Reflector 实例直接透传，绝不退化成 “读取 Reflection 类自身的属性”。
        if ($target instanceof Reflector) {
            return $target;
        }

        if ($target instanceof Closure) {
            return new ReflectionFunction($target);
        }

        if (is_object($target)) {
            return new ReflectionObject($target);
        }

        if (is_array($target)) {
            return self::fromCallableArray($target);
        }

        if (is_string($target)) {
            return self::fromString($target);
        }

        throw InvalidTargetException::forValue($target);
    }

    /**
     * 将任意目标归一化为类级反射实例。
     *
     * 成员级反射（方法、属性、常量、参数）会回退到其声明类。
     *
     * @param mixed $target 目标
     * @return ReflectionClass<object> 类反射实例
     * @throws InvalidTargetException 目标类型不受支持
     * @throws TargetNotFoundException 类不存在
     */
    public static function resolveClass(mixed $target): ReflectionClass
    {
        if (is_string($target)) {
            return self::classReflection($target);
        }

        $ref = self::resolve($target);

        if ($ref instanceof ReflectionClass) {
            return $ref;
        }

        $declaring = match (true) {
            $ref instanceof ReflectionMethod,
            $ref instanceof ReflectionProperty,
            $ref instanceof ReflectionClassConstant => $ref->getDeclaringClass(),
            $ref instanceof ReflectionParameter => $ref->getDeclaringClass(),
            default => null,
        };

        if ($declaring === null) {
            throw InvalidTargetException::forValue($target);
        }

        return $declaring;
    }

    /**
     * 解析方法反射。
     *
     * @param object|string $class 类名或对象
     * @param string $method 方法名
     * @throws TargetNotFoundException 类或方法不存在
     */
    public static function method(object|string $class, string $method): ReflectionMethod
    {
        $ref = self::resolveClass($class);

        if (!$ref->hasMethod($method)) {
            throw TargetNotFoundException::missingMethod($ref->getName(), $method);
        }

        return $ref->getMethod($method);
    }

    /**
     * 解析属性反射（含父类私有属性）。
     *
     * @param object|string $class 类名或对象
     * @param string $property 属性名
     * @throws TargetNotFoundException 类或属性不存在
     */
    public static function property(object|string $class, string $property): ReflectionProperty
    {
        $ref = self::resolveClass($class);

        if ($ref->hasProperty($property)) {
            return $ref->getProperty($property);
        }

        // ReflectionClass::hasProperty() 不会命中父类的 private 属性，需要手动上溯。
        for ($parent = $ref->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            if ($parent->hasProperty($property)) {
                return $parent->getProperty($property);
            }
        }

        throw TargetNotFoundException::missingProperty($ref->getName(), $property);
    }

    /**
     * 解析类常量反射（枚举 case 会返回 ReflectionEnumUnitCase）。
     *
     * @param object|string $class 类名或对象
     * @param string $constant 常量名
     * @throws TargetNotFoundException 类或常量不存在
     */
    public static function constant(object|string $class, string $constant): ReflectionClassConstant
    {
        $ref = self::resolveClass($class);

        if ($ref->isEnum()) {
            $enum = new ReflectionEnum($ref->getName());
            if ($enum->hasCase($constant)) {
                return $enum->getCase($constant);
            }
        }

        if (!$ref->hasConstant($constant)) {
            throw TargetNotFoundException::missingConstant($ref->getName(), $constant);
        }

        $constRef = $ref->getReflectionConstant($constant);

        if ($constRef === false) {
            throw TargetNotFoundException::missingConstant($ref->getName(), $constant);
        }

        return $constRef;
    }

    /**
     * 解析函数反射。
     *
     * @param string|Closure $function 函数名或闭包
     * @throws TargetNotFoundException 函数不存在
     */
    public static function function(string|Closure $function): ReflectionFunction
    {
        if ($function instanceof Closure) {
            return new ReflectionFunction($function);
        }

        $name = self::stripCallSuffix($function);

        if (!function_exists($name)) {
            throw TargetNotFoundException::missingFunction($name);
        }

        return new ReflectionFunction($name);
    }

    /**
     * 解析枚举 case 反射。
     *
     * @param UnitEnum $case 枚举 case 实例
     */
    public static function enumCase(UnitEnum $case): ReflectionEnumUnitCase
    {
        return (new ReflectionEnum($case::class))->getCase($case->name);
    }

    /**
     * 从函数/方法中解析指定参数。
     *
     * @param ReflectionFunctionAbstract $function 函数或方法反射
     * @param string|int $parameter 参数名或位置
     * @throws TargetNotFoundException 参数不存在
     */
    public static function parameter(ReflectionFunctionAbstract $function, string|int $parameter): ReflectionParameter
    {
        foreach ($function->getParameters() as $param) {
            if (is_int($parameter) ? $param->getPosition() === $parameter : $param->getName() === $parameter) {
                return $param;
            }
        }

        throw TargetNotFoundException::missingParameter(self::describe($function), (string) $parameter);
    }

    /**
     * 生成防碰撞的缓存键。
     *
     * 1.x 的参数缓存键只用了函数名，不同类的同名方法会互相覆盖；
     * 这里统一带上声明类与成员种类前缀，并转义匿名类名中的 NUL 字节，
     * 以保证外部缓存驱动（Redis / 文件）也能安全使用。
     *
     * @param Reflector $ref 反射实例
     * @return string 缓存键
     */
    public static function keyFor(Reflector $ref): string
    {
        $key = match (true) {
            $ref instanceof ReflectionEnumUnitCase => 'case:' . $ref->getDeclaringClass()->getName() . '::' . $ref->getName(),
            $ref instanceof ReflectionClassConstant => 'const:' . $ref->class . '::' . $ref->getName(),
            $ref instanceof ReflectionClass => 'class:' . $ref->getName(),
            $ref instanceof ReflectionMethod => 'method:' . $ref->class . '::' . $ref->getName(),
            $ref instanceof ReflectionProperty => 'prop:' . $ref->class . '::$' . $ref->getName(),
            $ref instanceof ReflectionFunction => 'func:' . self::functionKey($ref),
            $ref instanceof ReflectionParameter => 'param:' . self::functionKey($ref->getDeclaringFunction())
                . '#' . $ref->getPosition() . ':' . $ref->getName(),
            default => 'ref:' . $ref::class . ':' . spl_object_id($ref),
        };

        return str_contains($key, "\0") ? str_replace("\0", '%00', $key) : $key;
    }

    /**
     * 判断该反射目标的读取结果是否可安全缓存。
     *
     * 闭包（及其参数）无法生成稳定标识，不缓存以避免串味。
     *
     * @param Reflector $ref 反射实例
     */
    public static function isCacheable(Reflector $ref): bool
    {
        if ($ref instanceof ReflectionParameter) {
            return self::isCacheable($ref->getDeclaringFunction());
        }

        if ($ref instanceof ReflectionFunction) {
            return !$ref->isClosure();
        }

        return $ref instanceof ReflectionClass
            || $ref instanceof ReflectionMethod
            || $ref instanceof ReflectionProperty
            || $ref instanceof ReflectionClassConstant;
    }

    /**
     * 生成人类可读的目标描述，用于异常信息与日志。
     *
     * @param Reflector|null $ref 反射实例
     */
    public static function describe(?Reflector $ref): string
    {
        if ($ref === null) {
            return '未知目标';
        }

        return match (true) {
            $ref instanceof ReflectionEnumUnitCase => sprintf('枚举 case %s::%s', $ref->getDeclaringClass()->getName(), $ref->getName()),
            $ref instanceof ReflectionClassConstant => sprintf('类常量 %s::%s', $ref->class, $ref->getName()),
            $ref instanceof ReflectionClass => sprintf('类 %s', $ref->getName()),
            $ref instanceof ReflectionMethod => sprintf('方法 %s::%s()', $ref->class, $ref->getName()),
            $ref instanceof ReflectionProperty => sprintf('属性 %s::$%s', $ref->class, $ref->getName()),
            $ref instanceof ReflectionFunction => sprintf('函数 %s()', $ref->getName()),
            $ref instanceof ReflectionParameter => sprintf('参数 $%s（%s）', $ref->getName(), self::describe($ref->getDeclaringFunction())),
            default => sprintf('反射对象 %s', $ref::class),
        };
    }

    /**
     * 判断是否为匿名类。
     */
    public static function isAnonymous(ReflectionClass $ref): bool
    {
        return $ref->isAnonymous();
    }

    /**
     * 从可调用数组解析方法反射。
     *
     * @param array<mixed> $target 可调用数组
     */
    private static function fromCallableArray(array $target): ReflectionMethod
    {
        $values = array_values($target);

        if (count($values) !== 2 || !is_string($values[1])) {
            throw InvalidTargetException::forCallable($target);
        }

        [$holder, $method] = $values;

        if (!is_object($holder) && !is_string($holder)) {
            throw InvalidTargetException::forCallable($target);
        }

        return self::method($holder, self::stripCallSuffix($method));
    }

    /**
     * 从字符串解析反射目标。
     */
    private static function fromString(string $target): Reflector
    {
        $trimmed = trim($target);

        if ($trimmed === '') {
            throw InvalidTargetException::forString($target);
        }

        if (!str_contains($trimmed, '::')) {
            return self::fromPlainString($trimmed);
        }

        [$class, $member] = explode('::', $trimmed, 2);
        $class = trim($class);
        $member = trim($member);

        if ($class === '' || $member === '') {
            throw InvalidTargetException::forString($target);
        }

        // Foo::class
        if ($member === 'class') {
            return self::classReflection($class);
        }

        // Foo::$property
        if (str_starts_with($member, '$')) {
            return self::property($class, substr($member, 1));
        }

        // Foo::method($param)
        if (preg_match('/^([A-Za-z_\x80-\xff][\w\x80-\xff]*)\s*\(\s*\$?([A-Za-z_\x80-\xff][\w\x80-\xff]*)\s*\)$/', $member, $matches) === 1) {
            return self::parameter(self::method($class, $matches[1]), $matches[2]);
        }

        // Foo::method()
        if (str_ends_with($member, '()')) {
            return self::method($class, self::stripCallSuffix($member));
        }

        $ref = self::classReflection($class);

        if ($ref->hasMethod($member)) {
            return $ref->getMethod($member);
        }

        if ($ref->hasConstant($member) || ($ref->isEnum() && (new ReflectionEnum($ref->getName()))->hasCase($member))) {
            return self::constant($ref->getName(), $member);
        }

        throw TargetNotFoundException::missingMember($ref->getName(), $member);
    }

    /**
     * 解析不含 "::" 的字符串目标。
     */
    private static function fromPlainString(string $target): Reflector
    {
        if (self::typeExists($target)) {
            return new ReflectionClass($target);
        }

        $function = self::stripCallSuffix($target);

        if (function_exists($function)) {
            return new ReflectionFunction($function);
        }

        // 触发一次自动加载后再判定，兼容尚未加载的类型。
        if (class_exists($target)) {
            return new ReflectionClass($target);
        }

        throw TargetNotFoundException::unresolvable($target);
    }

    /**
     * 创建类反射，类型不存在时抛出明确异常。
     *
     * @param string $class 类型名
     * @return ReflectionClass<object>
     */
    private static function classReflection(string $class): ReflectionClass
    {
        $name = ltrim(trim($class), '\\');

        if ($name === '') {
            throw InvalidTargetException::forString($class);
        }

        if (!self::typeExists($name)) {
            throw TargetNotFoundException::missingClass($name);
        }

        return new ReflectionClass($name);
    }

    /**
     * 判断类型（类/接口/特性/枚举）是否存在。
     */
    private static function typeExists(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name);
    }

    /**
     * 去掉字符串结尾的调用括号。
     */
    private static function stripCallSuffix(string $name): string
    {
        $name = trim($name);

        return str_ends_with($name, '()') ? substr($name, 0, -2) : $name;
    }

    /**
     * 生成函数/方法的稳定标识。
     */
    private static function functionKey(ReflectionFunctionAbstract $function): string
    {
        if ($function instanceof ReflectionMethod) {
            return $function->class . '::' . $function->getName();
        }

        $name = $function->getName();

        if ($function instanceof ReflectionFunction && $function->isClosure()) {
            return sprintf('%s@%s:%d', $name, (string) $function->getFileName(), (int) $function->getStartLine());
        }

        return $name;
    }
}
