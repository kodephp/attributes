<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Attribute metadata wrapper.
 * 
 * Wraps a ReflectionAttribute instance and provides convenient access
 * to attribute information and instantiation.
 * 
 * @template-covariant T of object
 * @package Kode\Attributes
 */
final class Meta
{
    /**
     * The ReflectionAttribute instance.
     * 
     * @var \ReflectionAttribute
     */
    public readonly \ReflectionAttribute $refAttr;

    /**
     * The attribute class name.
     * 
     * @var string
     */
    public readonly string $name;

    /**
     * The attribute arguments.
     * 
     * @var array
     */
    public readonly array $args;

    /**
     * The instantiated attribute object.
     * 
     * @var object|null
     */
    private ?object $instance = null;

    /**
     * Create a new Meta instance.
     * 
     * @param \ReflectionAttribute $refAttr The reflection attribute
     */
    public function __construct(\ReflectionAttribute $refAttr)
    {
        $this->refAttr = $refAttr;
        $this->name = $refAttr->getName();
        $this->args = $refAttr->getArguments();
    }

    /**
     * Get the instantiated attribute object.
     * 
     * @return T
     * @throws \ReflectionException
     */
    public function getInstance(): object
    {
        if ($this->instance === null) {
            try {
                $this->instance = $this->refAttr->newInstance();
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "Failed to instantiate attribute '{$this->name}': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
        
        return $this->instance;
    }

    /**
     * Check if the attribute is repeatable.
     * 
     * @return bool
     */
    public function isRepeatable(): bool
    {
        $reflection = new \ReflectionClass($this->name);
        $attribute = $reflection->getAttributes(\Attribute::class);
        
        if (empty($attribute)) {
            return false;
        }
        
        $attr = $attribute[0]->newInstance();
        return ($attr->flags & \Attribute::IS_REPEATABLE) === \Attribute::IS_REPEATABLE;
    }

    /**
     * Get the target type of this attribute.
     * 
     * @return Target
     */
    public function getTarget(): Target
    {
        try {
            $reflection = new \ReflectionClass($this->name);
            $attributes = $reflection->getAttributes(\Attribute::class);
            
            if (empty($attributes)) {
                return Target::All;
            }
            
            $attr = $attributes[0]->newInstance();
            $flags = $attr->flags ?? 0;
            
            // Convert bit flags to Target enum
            return match (true) {
                ($flags & \Attribute::TARGET_CLASS) === \Attribute::TARGET_CLASS => Target::Class,
                ($flags & \Attribute::TARGET_FUNCTION) === \Attribute::TARGET_FUNCTION => Target::Function,
                ($flags & \Attribute::TARGET_METHOD) === \Attribute::TARGET_METHOD => Target::Method,
                ($flags & \Attribute::TARGET_PROPERTY) === \Attribute::TARGET_PROPERTY => Target::Property,
                ($flags & \Attribute::TARGET_PARAMETER) === \Attribute::TARGET_PARAMETER => Target::Parameter,
                default => Target::All,
            };
        } catch (\Throwable $e) {
            // If we can't determine the target, return All as a safe default
            return Target::All;
        }
    }
}