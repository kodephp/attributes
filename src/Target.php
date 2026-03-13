<?php

declare(strict_types=1);

namespace Kode\Attributes;

use Attribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionClassConstant;

/**
 * 属性目标类型枚举。
 * 
 * 定义属性可以应用的目标类型，支持位掩码组合。
 * 与PHP原生Attribute标志位兼容。
 * 
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
enum Target: int
{
    /**
     * 类目标。
     */
    case Clazz = 1;

    /**
     * 函数目标。
     */
    case Function = 2;

    /**
     * 方法目标。
     */
    case Method = 4;

    /**
     * 属性目标。
     */
    case Property = 8;

    /**
     * 类常量目标。
     */
    case ClassConstant = 16;

    /**
     * 参数目标。
     */
    case Parameter = 32;

    /**
     * 所有目标。
     */
    case All = 63;

    /**
     * 从Reflector实例创建Target。
     * 
     * @param \Reflector $ref 反射实例
     * @return self 目标类型枚举
     */
    public static function fromRef(\Reflector $ref): self
    {
        return match (true) {
            $ref instanceof ReflectionClass => self::Clazz,
            $ref instanceof ReflectionFunction => self::Function,
            $ref instanceof ReflectionMethod => self::Method,
            $ref instanceof ReflectionProperty => self::Property,
            $ref instanceof ReflectionClassConstant => self::ClassConstant,
            $ref instanceof ReflectionParameter => self::Parameter,
            default => self::All,
        };
    }

    /**
     * 从PHP Attribute标志位创建Target。
     * 
     * @param int $flags Attribute标志位
     * @return self 目标类型枚举
     */
    public static function fromAttributeFlags(int $flags): self
    {
        $targets = [];
        
        if (($flags & Attribute::TARGET_CLASS) === Attribute::TARGET_CLASS) {
            $targets[] = self::Clazz;
        }
        if (($flags & Attribute::TARGET_FUNCTION) === Attribute::TARGET_FUNCTION) {
            $targets[] = self::Function;
        }
        if (($flags & Attribute::TARGET_METHOD) === Attribute::TARGET_METHOD) {
            $targets[] = self::Method;
        }
        if (($flags & Attribute::TARGET_PROPERTY) === Attribute::TARGET_PROPERTY) {
            $targets[] = self::Property;
        }
        if (($flags & Attribute::TARGET_CLASS_CONSTANT) === Attribute::TARGET_CLASS_CONSTANT) {
            $targets[] = self::ClassConstant;
        }
        if (($flags & Attribute::TARGET_PARAMETER) === Attribute::TARGET_PARAMETER) {
            $targets[] = self::Parameter;
        }
        
        if (empty($targets) || count($targets) === 6) {
            return self::All;
        }
        
        if (count($targets) === 1) {
            return $targets[0];
        }
        
        return self::createCombined($targets);
    }

    /**
     * 检查是否支持指定目标。
     * 
     * @param self $target 要检查的目标
     * @return bool 是否支持
     */
    public function supports(self $target): bool
    {
        if ($this === self::All) {
            return true;
        }
        
        return ($this->value & $target->value) === $target->value;
    }

    /**
     * 合并多个目标类型。
     * 
     * @param self ...$targets 要合并的目标类型
     * @return self 合并后的目标类型
     */
    public function combine(self ...$targets): self
    {
        $allTargets = [$this, ...$targets];
        $uniqueTargets = [];
        $seenValues = [];
        
        foreach ($allTargets as $target) {
            if ($target === self::All) {
                return self::All;
            }
            
            foreach (self::getIndividualTargets() as $individual) {
                if ($target->supports($individual) && !isset($seenValues[$individual->value])) {
                    $uniqueTargets[] = $individual;
                    $seenValues[$individual->value] = true;
                }
            }
        }
        
        if (count($uniqueTargets) === 6) {
            return self::All;
        }
        
        if (count($uniqueTargets) === 1) {
            return $uniqueTargets[0];
        }
        
        return self::createCombined($uniqueTargets);
    }

    /**
     * 获取所有包含的目标类型列表。
     * 
     * @return array<self> 目标类型数组
     */
    public function getTargets(): array
    {
        if ($this === self::All) {
            return self::getIndividualTargets();
        }
        
        $targets = [];
        
        foreach (self::getIndividualTargets() as $target) {
            if ($this->supports($target)) {
                $targets[] = $target;
            }
        }
        
        return $targets;
    }

    /**
     * 转换为PHP Attribute标志位。
     * 
     * @return int Attribute标志位
     */
    public function toAttributeFlags(): int
    {
        $flags = 0;
        
        foreach ($this->getTargets() as $target) {
            $flags |= match ($target) {
                self::Clazz => Attribute::TARGET_CLASS,
                self::Function => Attribute::TARGET_FUNCTION,
                self::Method => Attribute::TARGET_METHOD,
                self::Property => Attribute::TARGET_PROPERTY,
                self::ClassConstant => Attribute::TARGET_CLASS_CONSTANT,
                self::Parameter => Attribute::TARGET_PARAMETER,
                self::All => Attribute::TARGET_ALL,
            };
        }
        
        return $flags;
    }

    /**
     * 获取目标类型的中文名称。
     * 
     * @return string 中文名称
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Clazz => '类',
            self::Function => '函数',
            self::Method => '方法',
            self::Property => '属性',
            self::ClassConstant => '类常量',
            self::Parameter => '参数',
            self::All => '全部',
        };
    }

    /**
     * 转换为字符串表示。
     * 
     * @return string 字符串表示
     */
    public function toString(): string
    {
        return implode('|', array_map(
            fn(self $t) => $t->name,
            $this->getTargets()
        ));
    }

    /**
     * 获取所有独立目标类型。
     * 
     * @return array<self> 独立目标类型数组
     */
    private static function getIndividualTargets(): array
    {
        return [
            self::Clazz,
            self::Function,
            self::Method,
            self::Property,
            self::ClassConstant,
            self::Parameter,
        ];
    }

    /**
     * 创建组合目标类型。
     * 
     * @param array<self> $targets 目标类型数组
     * @return self 组合后的目标类型
     */
    private static function createCombined(array $targets): self
    {
        $value = 0;
        foreach ($targets as $target) {
            $value |= $target->value;
        }
        
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }
        
        return self::All;
    }
}
