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

namespace Kode\Attributes\Exception;

use InvalidArgumentException;

/**
 * 目标参数非法异常。
 *
 * 当传入的读取目标既不是类名字符串、对象、闭包、可调用数组，
 * 也不是任何受支持的 Reflector 实例时抛出。
 *
 * @package Kode\Attributes\Exception
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final class InvalidTargetException extends InvalidArgumentException implements AttributeException
{
    /**
     * 针对非法值创建异常。
     *
     * @param mixed $target 非法目标
     */
    public static function forValue(mixed $target): self
    {
        return new self(sprintf(
            '无法解析属性读取目标：不支持的类型 %s。'
            . '受支持的目标为：类/接口/特性/枚举名、对象实例、Closure、[$obj, \'method\'] 可调用数组，'
            . '以及 ReflectionClass / ReflectionObject / ReflectionEnum / ReflectionMethod / '
            . 'ReflectionProperty / ReflectionClassConstant / ReflectionFunction / ReflectionParameter 实例。',
            get_debug_type($target)
        ));
    }

    /**
     * 针对无法解析的字符串创建异常。
     *
     * @param string $target 非法目标字符串
     */
    public static function forString(string $target): self
    {
        return new self(sprintf(
            '无法解析属性读取目标字符串 "%s"。'
            . '受支持的写法：Foo\\Bar、Foo\\Bar::method、Foo\\Bar::method()、Foo\\Bar::$property、'
            . 'Foo\\Bar::CONSTANT、Foo\\Bar::method($param)、functionName()。',
            $target
        ));
    }

    /**
     * 针对非法可调用数组创建异常。
     *
     * @param array<mixed> $target 非法可调用数组
     */
    public static function forCallable(array $target): self
    {
        return new self(sprintf(
            '无法解析属性读取目标：可调用数组必须形如 [对象或类名, 方法名]，当前收到 %d 个元素。',
            count($target)
        ));
    }
}
