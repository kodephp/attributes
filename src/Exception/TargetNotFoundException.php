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

use RuntimeException;

/**
 * 目标不存在异常。
 *
 * 当目标语法合法但实际不存在（类未加载、方法/属性/常量拼写错误等）时抛出。
 * 显式抛错而非静默返回空集合，是为了避免"属性注入无声失效"这类难以排查的问题。
 *
 * @package Kode\Attributes\Exception
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final class TargetNotFoundException extends RuntimeException implements AttributeException
{
    /**
     * 类不存在。
     */
    public static function missingClass(string $class): self
    {
        return new self(sprintf('类型 "%s" 不存在或无法自动加载。', $class));
    }

    /**
     * 方法不存在。
     */
    public static function missingMethod(string $class, string $method): self
    {
        return new self(sprintf('方法 "%s::%s()" 不存在。', $class, $method));
    }

    /**
     * 属性不存在。
     */
    public static function missingProperty(string $class, string $property): self
    {
        return new self(sprintf('属性 "%s::$%s" 不存在。', $class, $property));
    }

    /**
     * 类常量不存在。
     */
    public static function missingConstant(string $class, string $constant): self
    {
        return new self(sprintf('类常量 "%s::%s" 不存在。', $class, $constant));
    }

    /**
     * 函数不存在。
     */
    public static function missingFunction(string $function): self
    {
        return new self(sprintf('函数 "%s()" 不存在。', $function));
    }

    /**
     * 参数不存在。
     */
    public static function missingParameter(string $function, string $parameter): self
    {
        return new self(sprintf('函数/方法 "%s" 不存在名为 "$%s" 的参数。', $function, $parameter));
    }

    /**
     * 类成员既非方法也非常量。
     */
    public static function missingMember(string $class, string $member): self
    {
        return new self(sprintf(
            '"%s::%s" 既不是方法也不是类常量；若想读取属性请写作 "%s::$%s"。',
            $class,
            $member,
            $class,
            $member
        ));
    }

    /**
     * 整体无法解析。
     */
    public static function unresolvable(string $target): self
    {
        return new self(sprintf('目标 "%s" 既不是已存在的类型，也不是已定义的函数。', $target));
    }
}
