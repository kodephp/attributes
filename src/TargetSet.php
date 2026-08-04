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

use ArrayIterator;
use Attribute;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Reflector;
use Stringable;
use Traversable;

/**
 * 属性目标集合（位掩码）。
 *
 * `Target` 枚举无法表达任意组合（例如 类|方法 = 5 没有对应 case），
 * 1.x 中这类组合会退化成 `Target::All`，导致 `supportsTarget()` 恒真、目标校验失效。
 * `TargetSet` 用位掩码精确表达任意组合，且与 PHP 原生 `Attribute::TARGET_*` 标志位完全等价。
 *
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
final readonly class TargetSet implements Countable, IteratorAggregate, JsonSerializable, Stringable
{
    /**
     * 全部目标的掩码值（与 Attribute::TARGET_ALL 相同）。
     */
    public const int ALL_MASK = 63;

    /**
     * 目标位掩码。
     */
    public int $mask;

    /**
     * 创建目标集合。
     *
     * @param int $mask 位掩码，超出范围的位会被自动裁剪
     */
    public function __construct(int $mask)
    {
        $this->mask = $mask & self::ALL_MASK;
    }

    /**
     * 由若干 Target 枚举创建集合。
     */
    public static function of(Target ...$targets): self
    {
        $mask = 0;
        foreach ($targets as $target) {
            $mask |= $target->value;
        }

        return new self($mask);
    }

    /**
     * 由 PHP 原生 Attribute 标志位创建集合。
     *
     * @param int $flags Attribute::TARGET_* 组合
     */
    public static function fromAttributeFlags(int $flags): self
    {
        return new self($flags);
    }

    /**
     * 由反射对象推断其自身所处的目标类型。
     */
    public static function fromRef(Reflector $ref): self
    {
        return new self(Target::fromRef($ref)->value);
    }

    /**
     * 全部目标。
     */
    public static function all(): self
    {
        return new self(self::ALL_MASK);
    }

    /**
     * 空集合。
     */
    public static function none(): self
    {
        return new self(0);
    }

    /**
     * 是否包含指定目标。
     */
    public function has(Target $target): bool
    {
        return ($this->mask & $target->value) === $target->value;
    }

    /**
     * 是否覆盖给定目标（或目标集合）的全部位。
     */
    public function supports(Target|self $target): bool
    {
        $value = $target instanceof self ? $target->mask : $target->value;

        return $value !== 0 && ($this->mask & $value) === $value;
    }

    /**
     * 是否与给定目标集合存在交集。
     */
    public function intersects(Target|self $target): bool
    {
        $value = $target instanceof self ? $target->mask : $target->value;

        return ($this->mask & $value) !== 0;
    }

    /**
     * 追加目标，返回新实例。
     */
    public function with(Target ...$targets): self
    {
        $mask = $this->mask;
        foreach ($targets as $target) {
            $mask |= $target->value;
        }

        return new self($mask);
    }

    /**
     * 移除目标，返回新实例。
     */
    public function without(Target ...$targets): self
    {
        $mask = $this->mask;
        foreach ($targets as $target) {
            $mask &= ~$target->value;
        }

        return new self($mask);
    }

    /**
     * 与另一个集合求并集。
     */
    public function merge(self $other): self
    {
        return new self($this->mask | $other->mask);
    }

    /**
     * 与另一个集合求交集。
     */
    public function intersect(self $other): self
    {
        return new self($this->mask & $other->mask);
    }

    /**
     * 是否为全部目标。
     */
    public function isAll(): bool
    {
        return $this->mask === self::ALL_MASK;
    }

    /**
     * 是否为空集合。
     */
    public function isEmpty(): bool
    {
        return $this->mask === 0;
    }

    /**
     * 获取包含的所有目标枚举。
     *
     * @return array<int, Target>
     */
    public function targets(): array
    {
        $targets = [];
        foreach (Target::individual() as $target) {
            if ($this->has($target)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * 转为最接近的 Target 枚举；无法精确表达的组合返回 Target::All。
     */
    public function toTarget(): Target
    {
        return Target::tryFrom($this->mask) ?? Target::All;
    }

    /**
     * 转为 PHP 原生 Attribute 标志位。
     */
    public function toAttributeFlags(): int
    {
        return $this->mask === 0 ? Attribute::TARGET_ALL : $this->mask;
    }

    /**
     * 获取中文标签列表。
     *
     * @return array<int, string>
     */
    public function labels(): array
    {
        return array_map(static fn (Target $t): string => $t->getLabel(), $this->targets());
    }

    /**
     * 目标数量。
     */
    public function count(): int
    {
        return count($this->targets());
    }

    /**
     * 迭代器。
     *
     * @return Traversable<int, Target>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->targets());
    }

    /**
     * JSON 序列化表示。
     *
     * @return array{mask: int, targets: array<int, string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'mask' => $this->mask,
            'targets' => array_map(static fn (Target $t): string => $t->name, $this->targets()),
        ];
    }

    /**
     * 字符串表示，例如 "Clazz|Method"。
     */
    public function __toString(): string
    {
        if ($this->isEmpty()) {
            return 'None';
        }

        return implode('|', array_map(static fn (Target $t): string => $t->name, $this->targets()));
    }
}
