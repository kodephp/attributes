<?php

declare(strict_types=1);

namespace Kode\Attributes;

use ReflectionAttribute;
use ReflectionClass;
use Attribute;

/**
 * 属性元数据封装类。
 * 
 * 封装ReflectionAttribute实例，提供便捷的属性信息访问和实例化功能。
 * 支持协变泛型，允许子类安全返回。
 * 
 * @template-covariant T of object
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class Meta
{
    /**
     * 反射属性实例。
     */
    public readonly ReflectionAttribute $refAttr;

    /**
     * 所属反射对象（可选）。
     */
    public readonly ?\Reflector $reflector;

    /**
     * 属性类名。
     */
    public readonly string $name;

    /**
     * 属性参数。
     * 
     * @var array<int|string, mixed>
     */
    public readonly array $args;

    /**
     * 实例化后的属性对象（延迟加载）。
     */
    private ?object $instance = null;

    /**
     * 创建新的Meta实例。
     * 
     * @param ReflectionAttribute $refAttr 反射属性实例
     * @param \Reflector|null $reflector 所属反射对象
     */
    public function __construct(ReflectionAttribute $refAttr, ?\Reflector $reflector = null)
    {
        $this->refAttr = $refAttr;
        $this->reflector = $reflector;
        $this->name = $refAttr->getName();
        $this->args = $refAttr->getArguments();
    }

    /**
     * 获取实例化后的属性对象。
     * 
     * @return T 属性实例
     * @throws \RuntimeException 当属性实例化失败时抛出
     */
    public function getInstance(): object
    {
        if ($this->instance === null) {
            try {
                $this->instance = $this->refAttr->newInstance();
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "无法实例化属性 '{$this->name}': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
        
        return $this->instance;
    }

    /**
     * 获取实例化后的属性对象（newInstance别名）。
     * 
     * @return T 属性实例
     */
    public function newInstance(): object
    {
        return $this->getInstance();
    }

    /**
     * 检查属性是否可重复。
     * 
     * @return bool 是否可重复
     */
    public function isRepeatable(): bool
    {
        $reflection = new ReflectionClass($this->name);
        $attributes = $reflection->getAttributes(Attribute::class);
        
        if (empty($attributes)) {
            return false;
        }
        
        $attr = $attributes[0]->newInstance();
        return ($attr->flags & Attribute::IS_REPEATABLE) === Attribute::IS_REPEATABLE;
    }

    /**
     * 获取属性的目标类型。
     * 
     * @return Target 目标类型枚举
     */
    public function getTarget(): Target
    {
        try {
            $reflection = new ReflectionClass($this->name);
            $attributes = $reflection->getAttributes(Attribute::class);
            
            if (empty($attributes)) {
                return Target::All;
            }
            
            $attr = $attributes[0]->newInstance();
            $flags = $attr->flags ?? 0;
            
            return self::flagsToTarget($flags);
        } catch (\Throwable $e) {
            return Target::All;
        }
    }

    /**
     * 检查属性是否支持指定目标。
     * 
     * @param Target $target 要检查的目标类型
     * @return bool 是否支持
     */
    public function supportsTarget(Target $target): bool
    {
        $attrTarget = $this->getTarget();
        
        if ($attrTarget === Target::All) {
            return true;
        }
        
        return ($attrTarget->value & $target->value) === $target->value;
    }

    /**
     * 获取属性参数中的命名参数。
     * 
     * @return array<string, mixed> 命名参数数组
     */
    public function getNamedArguments(): array
    {
        $named = [];
        foreach ($this->args as $key => $value) {
            if (is_string($key)) {
                $named[$key] = $value;
            }
        }
        return $named;
    }

    /**
     * 获取属性参数中的位置参数。
     * 
     * @return array<int, mixed> 位置参数数组
     */
    public function getPositionalArguments(): array
    {
        $positional = [];
        foreach ($this->args as $key => $value) {
            if (is_int($key)) {
                $positional[] = $value;
            }
        }
        return $positional;
    }

    /**
     * 获取指定名称的参数值。
     * 
     * @param string $name 参数名
     * @param mixed $default 默认值
     * @return mixed 参数值
     */
    public function getArgument(string $name, mixed $default = null): mixed
    {
        return $this->args[$name] ?? $default;
    }

    /**
     * 获取属性所属的类名（如果适用）。
     * 
     * @return string|null 类名
     */
    public function getDeclaringClass(): ?string
    {
        if ($this->reflector === null) {
            return null;
        }
        
        return match (true) {
            $this->reflector instanceof \ReflectionClass => $this->reflector->getName(),
            $this->reflector instanceof \ReflectionMethod => $this->reflector->getDeclaringClass()->getName(),
            $this->reflector instanceof \ReflectionProperty => $this->reflector->getDeclaringClass()->getName(),
            $this->reflector instanceof \ReflectionClassConstant => $this->reflector->getDeclaringClass()->getName(),
            $this->reflector instanceof \ReflectionParameter => $this->reflector->getDeclaringClass()?->getName(),
            default => null,
        };
    }

    /**
     * 将Attribute标志位转换为Target枚举。
     * 
     * @param int $flags Attribute标志位
     * @return Target 目标类型枚举
     */
    private static function flagsToTarget(int $flags): Target
    {
        return Target::fromAttributeFlags($flags);
    }

    /**
     * 转换为数组表示。
     * 
     * @return array{ name: string, args: array, target: string } 属性信息数组
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'args' => $this->args,
            'target' => $this->getTarget()->name,
        ];
    }
}
