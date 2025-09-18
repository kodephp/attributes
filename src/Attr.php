<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Main facade for accessing attribute functionality.
 * 
 * Provides a simple static interface for common attribute operations.
 * 
 * @package Kode\Attributes
 */
final class Attr
{
    /**
     * Get the attribute reader instance.
     * 
     * @return Reader
     */
    public static function reader(): Reader
    {
        return new Reader();
    }

    /**
     * Get attributes for a target (class, method, property, etc.).
     * 
     * @param object|string $target The target to get attributes for
     * @return MetaList
     */
    public static function of(object|string $target): MetaList
    {
        return static::reader()->getAttributes($target);
    }

    /**
     * Check if a target has a specific attribute.
     * 
     * @param object|string $target The target to check
     * @param string $attrClass The attribute class to look for
     * @return bool
     */
    public static function has(object|string $target, string $attrClass): bool
    {
        return static::of($target)->has($attrClass);
    }

    /**
     * Get a specific attribute from a target.
     * 
     * @param object|string $target The target to get attribute from
     * @param string $attrClass The attribute class to get
     * @return Meta|null
     */
    public static function get(object|string $target, string $attrClass): ?Meta
    {
        return static::of($target)->get($attrClass);
    }
}