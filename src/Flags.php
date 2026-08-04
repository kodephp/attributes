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

use Attribute;

/**
 * 属性行为标志类。
 * 
 * 用于标记属性的行为特性，如是否继承、编译期处理、优先级等。
 * 框架可据此优化调度顺序和处理逻辑。
 * 
 * @package Kode\Attributes
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class Flags
{
    /**
     * 是否被子类继承。
     */
    public readonly bool $inherit;

    /**
     * 是否仅在编译期处理。
     */
    public readonly bool $compileTime;

    /**
     * 处理优先级（数值越大优先级越高）。
     */
    public readonly int $priority;

    /**
     * 是否缓存处理结果。
     */
    public readonly bool $cacheable;

    /**
     * 创建新的Flags实例。
     * 
     * @param bool $inherit 是否被子类继承
     * @param bool $compileTime 是否仅在编译期处理
     * @param int $priority 处理优先级
     * @param bool $cacheable 是否缓存处理结果
     */
    public function __construct(
        bool $inherit = false,
        bool $compileTime = false,
        int $priority = 0,
        bool $cacheable = true
    ) {
        $this->inherit = $inherit;
        $this->compileTime = $compileTime;
        $this->priority = $priority;
        $this->cacheable = $cacheable;
    }
    
    /**
     * 检查是否为默认标志配置。
     * 
     * @return bool 是否为默认配置
     */
    public function isDefault(): bool
    {
        return !$this->inherit 
            && !$this->compileTime 
            && $this->priority === 0 
            && $this->cacheable === true;
    }
    
    /**
     * 合并另一个Flags实例。
     * 
     * @param Flags $other 要合并的Flags实例
     * @return Flags 合并后的新实例
     */
    public function merge(self $other): self
    {
        return new self(
            $this->inherit || $other->inherit,
            $this->compileTime || $other->compileTime,
            max($this->priority, $other->priority),
            $this->cacheable && $other->cacheable
        );
    }

    /**
     * 创建继承标志实例。
     * 
     * @param int $priority 优先级
     * @return self
     */
    public static function inherit(int $priority = 0): self
    {
        return new self(inherit: true, priority: $priority);
    }

    /**
     * 创建编译期标志实例。
     * 
     * @param int $priority 优先级
     * @return self
     */
    public static function compileTime(int $priority = 0): self
    {
        return new self(compileTime: true, priority: $priority);
    }

    /**
     * 创建高优先级标志实例。
     * 
     * @param int $priority 优先级
     * @return self
     */
    public static function highPriority(int $priority = 100): self
    {
        return new self(priority: $priority);
    }

    /**
     * 创建不可缓存标志实例。
     * 
     * @return self
     */
    public static function nonCacheable(): self
    {
        return new self(cacheable: false);
    }
    
    /**
     * 获取字符串表示。
     * 
     * @return string 字符串表示
     */
    public function __toString(): string
    {
        $parts = [];
        
        if ($this->inherit) {
            $parts[] = '继承';
        }
        
        if ($this->compileTime) {
            $parts[] = '编译期';
        }
        
        if (!$this->cacheable) {
            $parts[] = '不缓存';
        }
        
        if ($this->priority !== 0) {
            $parts[] = "优先级:{$this->priority}";
        }
        
        return implode(', ', $parts) ?: '默认';
    }

    /**
     * 转换为数组表示。
     * 
     * @return array{inherit: bool, compileTime: bool, priority: int, cacheable: bool} 数组表示
     */
    public function toArray(): array
    {
        return [
            'inherit' => $this->inherit,
            'compileTime' => $this->compileTime,
            'priority' => $this->priority,
            'cacheable' => $this->cacheable,
        ];
    }

    /**
     * 从数组创建Flags实例。
     * 
     * @param array{inherit?: bool, compileTime?: bool, priority?: int, cacheable?: bool} $data 数据数组
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inherit: $data['inherit'] ?? false,
            compileTime: $data['compileTime'] ?? false,
            priority: $data['priority'] ?? 0,
            cacheable: $data['cacheable'] ?? true
        );
    }
}
