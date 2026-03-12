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

enum Target: int
{
    case Clazz = 1;
    case Function = 2;
    case Method = 4;
    case Property = 8;
    case ClassConstant = 16;
    case Parameter = 32;
    case All = 63;

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

    public function supports(self $target): bool
    {
        if ($this === self::All) {
            return true;
        }
        
        return ($this->value & $target->value) === $target->value;
    }

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

    public function toString(): string
    {
        return implode('|', array_map(
            fn(self $t) => $t->name,
            $this->getTargets()
        ));
    }

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
