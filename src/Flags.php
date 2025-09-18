<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Attribute flags.
 * 
 * Defines behavior flags for attributes.
 * 
 * @package Kode\Attributes
 */
#[\Attribute(\Attribute::IS_REPEATABLE)]
class Flags
{
    /**
     * Whether the attribute is inherited by child classes.
     * 
     * @var bool
     */
    public readonly bool $inherit;

    /**
     * Whether the attribute is only processed at compile time.
     * 
     * @var bool
     */
    public readonly bool $compileTime;

    /**
     * The priority of the attribute (higher values are processed first).
     * 
     * @var int
     */
    public readonly int $priority;

    /**
     * Create new flags.
     * 
     * @param bool $inherit Whether the attribute is inherited by child classes
     * @param bool $compileTime Whether the attribute is only processed at compile time
     * @param int $priority The priority of the attribute
     */
    public function __construct(
        bool $inherit = false,
        bool $compileTime = false,
        int $priority = 0
    ) {
        $this->inherit = $inherit;
        $this->compileTime = $compileTime;
        $this->priority = $priority;
    }
    
    /**
     * Check if this is the default set of flags.
     * 
     * @return bool
     */
    public function isDefault(): bool
    {
        return !$this->inherit && !$this->compileTime && $this->priority === 0;
    }
    
    /**
     * Merge with another set of flags.
     * 
     * @param Flags $other The other flags to merge with
     * @return Flags
     */
    public function merge(self $other): self
    {
        return new self(
            $this->inherit || $other->inherit,
            $this->compileTime || $other->compileTime,
            max($this->priority, $other->priority)
        );
    }
    
    /**
     * Get a string representation of the flags.
     * 
     * @return string
     */
    public function __toString(): string
    {
        $parts = [];
        
        if ($this->inherit) {
            $parts[] = 'inherit';
        }
        
        if ($this->compileTime) {
            $parts[] = 'compileTime';
        }
        
        if ($this->priority !== 0) {
            $parts[] = "priority:{$this->priority}";
        }
        
        return implode(', ', $parts) ?: 'none';
    }
}