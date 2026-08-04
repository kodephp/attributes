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
use ReflectionParameter;

/**
 * 属性读取器接口。
 *
 * 定义属性读取器的契约，支持从各种目标读取属性。
 *
 * 2.0 起所有方法都接受"宽目标"：类名字符串、对象实例，以及任意 Reflector 实例。
 *
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
interface ReaderInterface
{
    /**
     * 读取任意目标的属性。
     *
     * 支持：类名字符串、`Foo::method`、`Foo::$prop`、`Foo::CONST`、`Foo::method($arg)`、
     * 函数名、对象实例、Closure、`[$obj, 'method']`，以及任意 Reflector 实例。
     *
     * @param mixed $target 目标
     * @param bool $inherited 是否沿继承链（父类 / 接口 / 特性）合并读取
     * @return MetaList 属性元数据集合
     */
    public function read(mixed $target, bool $inherited = false): MetaList;

    /**
     * 获取类的属性。
     *
     * @param object|string $class 类名、对象或类反射
     * @param bool $inherited 是否沿继承链读取
     * @return MetaList 属性元数据集合
     */
    public function getClassAttrs(object|string $class, bool $inherited = false): MetaList;

    /**
     * 获取方法的属性。
     *
     * @param object|string $class 类名或对象
     * @param string $method 方法名
     * @param bool $inherited 是否沿继承链读取
     * @return MetaList 属性元数据集合
     */
    public function getMethodAttrs(object|string $class, string $method, bool $inherited = false): MetaList;

    /**
     * 获取类属性（property）的属性。
     *
     * @param object|string $class 类名或对象
     * @param string $property 属性名
     * @param bool $inherited 是否沿继承链读取
     * @return MetaList 属性元数据集合
     */
    public function getPropertyAttrs(object|string $class, string $property, bool $inherited = false): MetaList;

    /**
     * 获取类常量（含枚举 case）的属性。
     *
     * @param object|string $class 类名或对象
     * @param string $constant 常量名
     * @return MetaList 属性元数据集合
     */
    public function getConstantAttrs(object|string $class, string $constant): MetaList;

    /**
     * 获取函数的属性。
     *
     * @param string|Closure $function 函数名或闭包
     * @return MetaList 属性元数据集合
     */
    public function getFunctionAttrs(string|Closure $function): MetaList;

    /**
     * 获取参数的属性。
     *
     * @param ReflectionParameter|string|array{0: object|string, 1: string}|Closure $target 参数反射，或其所属函数/方法
     * @param string|int|null $parameter 当 $target 不是参数反射时，指定参数名或位置
     * @return MetaList 属性元数据集合
     */
    public function getParameterAttrs(
        ReflectionParameter|string|array|Closure $target,
        string|int|null $parameter = null
    ): MetaList;

    /**
     * 清除读取器缓存。
     */
    public function clearCache(): void;
}
