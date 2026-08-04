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

use Reflector;

/**
 * 链式属性检查器。
 *
 * 面向依赖注入、路由注册、事件发现等场景，提供围绕"单个目标"的流式 API：
 *
 * ```php
 * $props = Attr::on(UserService::class)
 *     ->inherited()
 *     ->properties(Inject::class);
 * ```
 *
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final readonly class Inspector
{
    /**
     * 创建检查器。
     *
     * @param Reader $reader 属性读取器
     * @param mixed $target 目标
     * @param bool $inheritedMode 是否沿继承链读取
     */
    public function __construct(
        private Reader $reader,
        private mixed $target,
        private bool $inheritedMode = false
    ) {
    }

    /**
     * 切换继承模式，返回新实例。
     *
     * @param bool $inherited 是否沿继承链读取
     */
    public function inherited(bool $inherited = true): self
    {
        return new self($this->reader, $this->target, $inherited);
    }

    /**
     * 获取归一化后的反射对象。
     */
    public function reflector(): Reflector
    {
        return TargetRef::resolve($this->target);
    }

    /**
     * 获取目标的人类可读描述。
     */
    public function describe(): string
    {
        return TargetRef::describe($this->reflector());
    }

    /**
     * 获取目标自身的全部属性。
     */
    public function attributes(): MetaList
    {
        return $this->reader->read($this->target, $this->inheritedMode);
    }

    /**
     * 是否存在指定属性。
     */
    public function has(string $attrClass): bool
    {
        return $this->attributes()->has($attrClass);
    }

    /**
     * 获取指定属性。
     */
    public function get(string $attrClass): ?Meta
    {
        return $this->attributes()->get($attrClass);
    }

    /**
     * 获取全部指定属性。
     */
    public function all(string $attrClass): MetaList
    {
        return $this->attributes()->getAll($attrClass);
    }

    /**
     * 获取指定属性的实例。
     */
    public function instance(string $attrClass): ?object
    {
        return $this->get($attrClass)?->getInstance();
    }

    /**
     * 获取全部指定属性的实例。
     *
     * @return array<int, object>
     */
    public function instances(string $attrClass): array
    {
        return $this->all($attrClass)->getInstances();
    }

    /**
     * 获取指定方法的属性。
     */
    public function method(string $method): MetaList
    {
        return $this->reader->getMethodAttrs($this->classTarget(), $method, $this->inheritedMode);
    }

    /**
     * 获取指定属性（property）的属性。
     */
    public function property(string $property): MetaList
    {
        return $this->reader->getPropertyAttrs($this->classTarget(), $property, $this->inheritedMode);
    }

    /**
     * 获取指定常量的属性。
     */
    public function constant(string $constant): MetaList
    {
        return $this->reader->getConstantAttrs($this->classTarget(), $constant);
    }

    /**
     * 获取全部带属性的方法。
     *
     * @param string|null $attrClass 属性类名过滤
     * @return array<string, MetaList> 方法名 => 属性集合
     */
    public function methods(?string $attrClass = null): array
    {
        return self::filter($this->reader->getAllMethodAttrs($this->classTarget(), $this->inheritedMode), $attrClass);
    }

    /**
     * 获取全部带属性的属性（property）。
     *
     * @param string|null $attrClass 属性类名过滤
     * @return array<string, MetaList> 属性名 => 属性集合
     */
    public function properties(?string $attrClass = null): array
    {
        return self::filter($this->reader->getAllPropertyAttrs($this->classTarget(), $this->inheritedMode), $attrClass);
    }

    /**
     * 获取全部带属性的常量。
     *
     * @param string|null $attrClass 属性类名过滤
     * @return array<string, MetaList> 常量名 => 属性集合
     */
    public function constants(?string $attrClass = null): array
    {
        return self::filter($this->reader->getAllConstantAttrs($this->classTarget()), $attrClass);
    }

    /**
     * 获取指定方法全部带属性的参数。
     *
     * @param string|null $method 方法名；目标本身为函数/方法时可省略
     * @param string|null $attrClass 属性类名过滤
     * @return array<string, MetaList> 参数名 => 属性集合
     */
    public function parameters(?string $method = null, ?string $attrClass = null): array
    {
        $target = $method === null ? $this->target : $this->classTarget();

        /** @var object|string $target */
        return self::filter($this->reader->getAllParameterAttrs($target, $method), $attrClass);
    }

    /**
     * 获取底层 Reader。
     */
    public function reader(): Reader
    {
        return $this->reader;
    }

    /**
     * 将目标归一化为类名，供成员级读取使用。
     */
    private function classTarget(): string
    {
        return TargetRef::resolveClass($this->target)->getName();
    }

    /**
     * 按属性类名过滤映射。
     *
     * @param array<string, MetaList> $groups 成员映射
     * @param string|null $attrClass 属性类名
     * @return array<string, MetaList> 过滤结果
     */
    private static function filter(array $groups, ?string $attrClass): array
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
