<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Attribute target types.
 * 
 * Defines the possible targets for attributes.
 * 
 * @package Kode\Attributes
 */
enum Target: int
{
    case Class = 1;
    case Function = 2;
    case Method = 4;
    case Property = 8;
    case Parameter = 16;
    case All = 31;

    /**
     * Create a Target from a Reflector instance.
     * 
     * @param \Reflector $ref The reflector instance
     * @return self
     */
    public static function fromRef(\Reflector $ref): self
    {
        return match (true) {
            $ref instanceof \ReflectionClass => self::Class,
            $ref instanceof \ReflectionFunction => self::Function,
            $ref instanceof \ReflectionMethod => self::Method,
            $ref instanceof \ReflectionProperty => self::Property,
            $ref instanceof \ReflectionParameter => self::Parameter,
            default => self::All,
        };
    }
}