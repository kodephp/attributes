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
use Generator;
use Kode\Attributes\Exception\AttributeInstantiationException;
use Kode\Attributes\Exception\InvalidTargetException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;
use Throwable;

/**
 * 属性读取器实现类。
 *
 * 使用 PHP 反射 API 读取各类目标（类、方法、属性、常量、函数、参数）的属性，
 * 并提供一致的访问接口。内置缓存机制，支持自定义缓存驱动。
 *
 * 2.0 关键变更：所有入口都经由 {@see TargetRef} 归一化目标，
 * 传入 `ReflectionClass` / `ReflectionProperty` / `ReflectionParameter` 时
 * 不再错误地去读取 Reflection 类自身的属性。
 *
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final class Reader implements ReaderInterface
{
    /**
     * 继承模式缓存键前缀。
     */
    private const string INHERIT_PREFIX = 'inherited|';

    /**
     * 缓存实现实例。
     */
    private CacheInterface $cache;

    /**
     * 严格模式：读取或实例化异常时抛出而非静默跳过。
     */
    private bool $strict;

    /**
     * 创建新的 Reader 实例。
     *
     * @param CacheInterface|null $cache 可选的缓存实现，默认使用 ArrayCache
     * @param bool $strict 严格模式，属性构造异常时抛出而非跳过
     */
    public function __construct(?CacheInterface $cache = null, bool $strict = false)
    {
        $this->cache = $cache ?? new ArrayCache();
        $this->strict = $strict;
    }

    /**
     * 是否处于严格模式。
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * 返回使用指定严格模式的新实例（共享同一缓存）。
     */
    public function withStrict(bool $strict = true): self
    {
        return new self($this->cache, $strict);
    }

    /**
     * 返回使用指定缓存的新实例。
     */
    public function withCache(CacheInterface $cache): self
    {
        return new self($cache, $this->strict);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function read(mixed $target, bool $inherited = false): MetaList
    {
        $ref = TargetRef::resolve($target);

        return $inherited ? $this->readInherited($ref) : $this->readOne($ref);
    }

    /**
     * 获取目标的属性（自动检测目标类型）。
     *
     * @param mixed $target 目标
     * @param bool $inherited 是否沿继承链读取
     */
    public function getAttributes(mixed $target, bool $inherited = false): MetaList
    {
        return $this->read($target, $inherited);
    }

    /**
     * 获取对象的类级属性。
     *
     * @param object $object 目标对象实例
     * @param bool $inherited 是否沿继承链读取
     */
    public function getObjectAttrs(object $object, bool $inherited = false): MetaList
    {
        return $this->getClassAttrs($object, $inherited);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getClassAttrs(object|string $class, bool $inherited = false): MetaList
    {
        $ref = TargetRef::resolveClass($class);

        return $inherited ? $this->readInherited($ref) : $this->readOne($ref);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getMethodAttrs(object|string $class, string $method, bool $inherited = false): MetaList
    {
        $ref = TargetRef::method($class, $method);

        return $inherited ? $this->readInherited($ref) : $this->readOne($ref);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getPropertyAttrs(object|string $class, string $property, bool $inherited = false): MetaList
    {
        $ref = TargetRef::property($class, $property);

        if (!$inherited) {
            // 非继承模式只认“直接声明在该类上的属性”，避免误读到父类的私有属性。
            $declared = $ref->getDeclaringClass()->getName();
            // 目标可以是类名 / 实例 / 反射对象，一律先归一化成类名再比对：
            // 直接 get_class($class) 会把 ReflectionClass 本身当成“请求的类”，判定永远不相等 → 静默返回空。
            $requested = TargetRef::resolveClass($class)->getName();

            if ($declared !== $requested) {
                return MetaList::empty();
            }
        }

        return $inherited ? $this->readInherited($ref) : $this->readOne($ref);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getConstantAttrs(object|string $class, string $constant): MetaList
    {
        return $this->readOne(TargetRef::constant($class, $constant));
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getFunctionAttrs(string|Closure $function): MetaList
    {
        return $this->readOne(TargetRef::function($function));
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getParameterAttrs(
        ReflectionParameter|string|array|Closure $target,
        string|int|null $parameter = null
    ): MetaList {
        if ($target instanceof ReflectionParameter) {
            return $this->readOne($target);
        }

        $ref = TargetRef::resolve($target);

        // 形如 'Foo::bar($baz)' 的字符串会直接解析为参数反射。
        if ($ref instanceof ReflectionParameter) {
            return $this->readOne($ref);
        }

        if (!$ref instanceof ReflectionFunctionAbstract || $parameter === null) {
            throw InvalidTargetException::forValue($target);
        }

        return $this->readOne(TargetRef::parameter($ref, $parameter));
    }

    /**
     * 获取类的所有方法属性。
     *
     * @param object|string $class 类名或对象
     * @param bool $inherited 是否沿继承链读取
     * @return array<string, MetaList> 方法名 => 属性集合
     */
    public function getAllMethodAttrs(object|string $class, bool $inherited = false): array
    {
        $ref = TargetRef::resolveClass($class);
        $key = 'all_methods:' . ($inherited ? 'i:' : '') . $ref->getName();

        return $this->cached($key, $ref, function () use ($ref, $inherited): array {
            $result = [];

            foreach ($ref->getMethods() as $method) {
                $attrs = $inherited ? $this->readInherited($method) : $this->readOne($method);

                if ($attrs->isNotEmpty()) {
                    $result[$method->getName()] = $attrs;
                }
            }

            return $result;
        });
    }

    /**
     * 获取类的所有属性（property）的属性。
     *
     * 继承模式下会额外收集父类的 **private 属性**——
     * `ReflectionClass::getProperties()` 不返回父类私有属性，
     * 这是属性注入在继承结构中"部分失效"的常见根因。
     *
     * @param object|string $class 类名或对象
     * @param bool $inherited 是否包含父类私有属性并沿继承链合并
     * @return array<string, MetaList> 属性名 => 属性集合
     */
    public function getAllPropertyAttrs(object|string $class, bool $inherited = false): array
    {
        $ref = TargetRef::resolveClass($class);
        $key = 'all_properties:' . ($inherited ? 'i:' : '') . $ref->getName();

        return $this->cached($key, $ref, function () use ($ref, $inherited): array {
            $result = [];

            foreach ($this->collectProperties($ref, $inherited) as $property) {
                $attrs = $inherited ? $this->readInherited($property) : $this->readOne($property);

                if ($attrs->isNotEmpty()) {
                    $result[$property->getName()] = $attrs;
                }
            }

            return $result;
        });
    }

    /**
     * 获取类的所有常量（含枚举 case）的属性。
     *
     * @param object|string $class 类名或对象
     * @return array<string, MetaList> 常量名 => 属性集合
     */
    public function getAllConstantAttrs(object|string $class): array
    {
        $ref = TargetRef::resolveClass($class);

        return $this->cached('all_constants:' . $ref->getName(), $ref, function () use ($ref): array {
            $result = [];

            foreach ($ref->getReflectionConstants() as $constant) {
                $attrs = $this->readOne($constant);

                if ($attrs->isNotEmpty()) {
                    $result[$constant->getName()] = $attrs;
                }
            }

            return $result;
        });
    }

    /**
     * 获取函数/方法所有参数的属性。
     *
     * @param object|string $target 类名、对象、闭包、函数名或函数反射
     *                              （ReflectionFunctionAbstract 也是 object，故归并到 object）
     * @param string|null $method 当 $target 为类/对象时的方法名
     * @return array<string, MetaList> 参数名 => 属性集合
     */
    public function getAllParameterAttrs(
        object|string $target,
        ?string $method = null
    ): array {
        $ref = match (true) {
            $target instanceof ReflectionFunctionAbstract => $target,
            $method !== null => TargetRef::method($target, $method),
            default => TargetRef::resolve($target),
        };

        if (!$ref instanceof ReflectionFunctionAbstract) {
            throw InvalidTargetException::forValue($target);
        }

        $result = [];

        foreach ($ref->getParameters() as $parameter) {
            $attrs = $this->readOne($parameter);

            if ($attrs->isNotEmpty()) {
                $result[$parameter->getName()] = $attrs;
            }
        }

        return $result;
    }

    /**
     * 收集类的所有属性反射（可含父类私有属性）。
     *
     * @param ReflectionClass<object> $ref 类反射
     * @param bool $inherited 是否上溯父类
     * @return array<string, ReflectionProperty> 属性名 => 属性反射
     */
    public function collectProperties(ReflectionClass $ref, bool $inherited = true): array
    {
        $properties = [];

        for ($current = $ref; $current !== false; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                // 子类同名属性优先，避免被父类声明覆盖。
                $properties[$property->getName()] ??= $property;
            }

            if (!$inherited) {
                break;
            }
        }

        return $properties;
    }

    /**
     * 清除所有缓存。
     */
    #[\Override]
    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * 获取缓存实例。
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * 读取单个反射目标的属性。
     */
    private function readOne(Reflector $ref): MetaList
    {
        if (!TargetRef::isCacheable($ref)) {
            return $this->build($ref);
        }

        /** @var MetaList */
        return $this->cache->get(TargetRef::keyFor($ref), fn (): MetaList => $this->build($ref));
    }

    /**
     * 沿继承链读取属性并合并去重。
     */
    private function readInherited(Reflector $ref): MetaList
    {
        $loader = function () use ($ref): MetaList {
            $metas = [];
            $seen = [];

            foreach ($this->hierarchyOf($ref) as $node) {
            foreach ($this->readOne($node) as $meta) {
                // 按“声明位置 + 属性类”去重，避免同一属性在父类/子类各声明一次时被误删，
                // 同时仍对非可重复属性去重（防止 trait/接口别名导致的重复声明）。
                if (!$meta->isRepeatable()) {
                    $uid = $meta->uid();

                    if (isset($seen[$uid])) {
                        continue;
                    }

                    $seen[$uid] = true;
                }

                $metas[] = $meta;
            }
            }

            return new MetaList($metas);
        };

        if (!TargetRef::isCacheable($ref)) {
            return $loader();
        }

        /** @var MetaList */
        return $this->cache->get(self::INHERIT_PREFIX . TargetRef::keyFor($ref), $loader);
    }

    /**
     * 生成目标的继承链（自身在前，越近的祖先越靠前）。
     *
     * @return Generator<int, Reflector>
     */
    private function hierarchyOf(Reflector $ref): Generator
    {
        yield $ref;

        if ($ref instanceof ReflectionClass) {
            yield from $this->classHierarchy($ref);

            return;
        }

        if ($ref instanceof ReflectionMethod) {
            yield from $this->memberHierarchy(
                $ref->getDeclaringClass(),
                static fn (ReflectionClass $c): ?Reflector => $c->hasMethod($ref->getName())
                    ? $c->getMethod($ref->getName())
                    : null,
                $ref
            );

            return;
        }

        if ($ref instanceof ReflectionProperty) {
            yield from $this->memberHierarchy(
                $ref->getDeclaringClass(),
                static fn (ReflectionClass $c): ?Reflector => $c->hasProperty($ref->getName())
                    ? $c->getProperty($ref->getName())
                    : null,
                $ref
            );

            return;
        }

        if ($ref instanceof ReflectionClassConstant) {
            yield from $this->memberHierarchy(
                $ref->getDeclaringClass(),
                static function (ReflectionClass $c) use ($ref): ?Reflector {
                    $constant = $c->getReflectionConstant($ref->getName());

                    return $constant === false ? null : $constant;
                },
                $ref
            );
        }
    }

    /**
     * 生成类的继承链：父类 -> 接口 -> 特性。
     *
     * @param ReflectionClass<object> $ref 类反射
     * @return Generator<int, ReflectionClass<object>>
     */
    private function classHierarchy(ReflectionClass $ref): Generator
    {
        $visited = [$ref->getName() => true];

        for ($parent = $ref->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            if (isset($visited[$parent->getName()])) {
                break;
            }

            $visited[$parent->getName()] = true;

            yield $parent;
        }

        foreach ($ref->getInterfaces() as $interface) {
            if (isset($visited[$interface->getName()])) {
                continue;
            }

            $visited[$interface->getName()] = true;

            yield $interface;
        }

        foreach ($this->allTraits($ref) as $trait) {
            if (isset($visited[$trait->getName()])) {
                continue;
            }

            $visited[$trait->getName()] = true;

            yield $trait;
        }
    }

    /**
     * 递归收集类及其父类使用的全部特性。
     *
     * @param ReflectionClass<object> $ref 类反射
     * @return array<string, ReflectionClass<object>>
     */
    private function allTraits(ReflectionClass $ref): array
    {
        $traits = [];

        for ($current = $ref; $current !== false; $current = $current->getParentClass()) {
            foreach ($current->getTraits() as $name => $trait) {
                if (isset($traits[$name])) {
                    continue;
                }

                $traits[$name] = $trait;

                foreach ($this->allTraits($trait) as $nestedName => $nested) {
                    $traits[$nestedName] ??= $nested;
                }
            }
        }

        return $traits;
    }

    /**
     * 生成类成员（方法/属性/常量）的继承链。
     *
     * @param ReflectionClass<object> $declaring 声明类
     * @param callable(ReflectionClass<object>): (Reflector|null) $resolve 成员解析
     * @param Reflector $self 当前成员反射
     * @return Generator<int, Reflector>
     */
    private function memberHierarchy(ReflectionClass $declaring, callable $resolve, Reflector $self): Generator
    {
        $seen = [TargetRef::keyFor($self) => true];

        foreach ($this->classHierarchy($declaring) as $ancestor) {
            $member = $resolve($ancestor);

            if ($member === null) {
                continue;
            }

            $key = TargetRef::keyFor($member);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            yield $member;
        }
    }

    /**
     * 从反射属性数组创建 MetaList 实例。
     *
     * @param array<int, ReflectionAttribute<object>> $attributes 反射属性数组
     * @param Reflector|null $reflector 所属反射对象
     */
    private function createMetaList(array $attributes, ?Reflector $reflector = null): MetaList
    {
        $metaList = [];

        foreach ($attributes as $attribute) {
            try {
                $metaList[] = new Meta($attribute, $reflector);
            } catch (Throwable $e) {
                if ($this->strict) {
                    throw AttributeInstantiationException::forSubject(TargetRef::describe($reflector), $e);
                }

                continue;
            }
        }

        return new MetaList($metaList);
    }

    /**
     * 直接从反射对象构建属性集合。
     */
    private function build(Reflector $ref): MetaList
    {
        if (!method_exists($ref, 'getAttributes')) {
            return MetaList::empty();
        }

        /** @var array<int, ReflectionAttribute<object>> $attributes */
        $attributes = $ref->getAttributes();

        return $this->createMetaList($attributes, $ref);
    }

    /**
     * 带缓存执行加载器（目标不可缓存时直接执行）。
     *
     * @template R
     * @param string $key 缓存键
     * @param Reflector $ref 目标反射
     * @param callable(): R $loader 加载器
     * @return R
     */
    private function cached(string $key, Reflector $ref, callable $loader): mixed
    {
        if (!TargetRef::isCacheable($ref)) {
            return $loader();
        }

        return $this->cache->get($key, $loader);
    }
}
