<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Collection of attribute metadata.
 * 
 * Provides a convenient way to work with collections of attributes,
 * including filtering, mapping, and other collection operations.
 * 
 * @template-covariant T of Meta
 * @implements \IteratorAggregate<int, T>
 * @package Kode\Attributes
 */
final class MetaList implements \Countable, \IteratorAggregate
{
    /**
     * The list of metadata objects.
     * 
     * @var array<T>
     */
    private array $list;

    /**
     * Create a new MetaList instance.
     * 
     * @param array<T> $list The list of metadata objects
     */
    public function __construct(array $list = [])
    {
        $this->list = $list;
    }

    /**
     * Get the first metadata object in the list.
     * 
     * @return T|null
     */
    public function first(): ?Meta
    {
        return $this->list[0] ?? null;
    }

    /**
     * Get the last metadata object in the list.
     * 
     * @return T|null
     */
    public function last(): ?Meta
    {
        $count = count($this->list);
        return $count > 0 ? $this->list[$count - 1] : null;
    }

    /**
     * Filter the metadata objects using a callback function.
     * 
     * @param callable $fn The filter callback
     * @return self<T>
     */
    public function filter(callable $fn): self
    {
        return new self(array_filter($this->list, $fn));
    }

    /**
     * Apply a callback function to each metadata object.
     * 
     * @param callable $fn The map callback
     * @return array
     */
    public function map(callable $fn): array
    {
        return array_map($fn, $this->list);
    }

    /**
     * Check if the list contains an attribute of the specified class.
     * 
     * @param string $className The attribute class name
     * @return bool
     */
    public function has(string $className): bool
    {
        foreach ($this->list as $meta) {
            if ($meta->name === $className) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the first attribute of the specified class.
     * 
     * @param string $className The attribute class name
     * @return T|null
     */
    public function get(string $className): ?Meta
    {
        foreach ($this->list as $meta) {
            if ($meta->name === $className) {
                return $meta;
            }
        }
        
        return null;
    }

    /**
     * Get all metadata objects as an array.
     * 
     * @return array<T>
     */
    public function all(): array
    {
        return $this->list;
    }

    /**
     * Merge this list with another MetaList.
     * 
     * @param self<T> $other The other metadata list
     * @return self<T>
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->list, $other->all()));
    }

    /**
     * Get the number of metadata objects in the list.
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * Get an iterator for the metadata objects.
     * 
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->list);
    }
}