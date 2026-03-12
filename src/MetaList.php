<?php

declare(strict_types=1);

namespace Kode\Attributes;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * 属性元数据集合类。
 * 
 * 提供便捷的属性集合操作，包括过滤、映射、遍历等功能。
 * 支持协变泛型，允许子类安全返回。
 * 
 * @template-covariant T of Meta
 * @implements IteratorAggregate<int, T>
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class MetaList implements Countable, IteratorAggregate
{
    /**
     * 元数据对象列表。
     * 
     * @var array<T>
     */
    private array $list;

    /**
     * 创建新的MetaList实例。
     * 
     * @param array<T> $list 元数据对象数组
     */
    public function __construct(array $list = [])
    {
        $this->list = array_values($list);
    }

    /**
     * 获取列表中的第一个元数据对象。
     * 
     * @return T|null 第一个元数据对象，空列表返回null
     */
    public function first(): ?Meta
    {
        return $this->list[0] ?? null;
    }

    /**
     * 获取列表中的最后一个元数据对象。
     * 
     * @return T|null 最后一个元数据对象，空列表返回null
     */
    public function last(): ?Meta
    {
        $count = count($this->list);
        return $count > 0 ? $this->list[$count - 1] : null;
    }

    /**
     * 使用回调函数过滤元数据对象。
     * 
     * @param callable(T): bool $fn 过滤回调函数
     * @return self<T> 过滤后的新集合
     */
    public function filter(callable $fn): self
    {
        return new self(array_filter($this->list, $fn));
    }

    /**
     * 对每个元数据对象应用回调函数。
     * 
     * @template R
     * @param callable(T): R $fn 映射回调函数
     * @return array<int, R> 映射结果数组
     */
    public function map(callable $fn): array
    {
        return array_map($fn, $this->list);
    }

    /**
     * 对每个元数据对象应用回调函数并展平结果。
     * 
     * @template R
     * @param callable(T): array<R> $fn 映射回调函数
     * @return array<R> 展平后的结果数组
     */
    public function flatMap(callable $fn): array
    {
        $result = [];
        foreach ($this->list as $item) {
            $result = array_merge($result, $fn($item));
        }
        return $result;
    }

    /**
     * 检查列表中是否包含指定类的属性。
     * 
     * @param string $className 属性类名
     * @return bool 是否存在
     */
    public function has(string $className): bool
    {
        foreach ($this->list as $meta) {
            if ($meta->name === $className || is_a($meta->name, $className, true)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 获取列表中第一个指定类的属性元数据。
     * 
     * @param string $className 属性类名
     * @return T|null 属性元数据，不存在返回null
     */
    public function get(string $className): ?Meta
    {
        foreach ($this->list as $meta) {
            if ($meta->name === $className || is_a($meta->name, $className, true)) {
                return $meta;
            }
        }
        
        return null;
    }

    /**
     * 获取列表中所有指定类的属性元数据。
     * 
     * @param string $className 属性类名
     * @return self<T> 属性元数据集合
     */
    public function getAll(string $className): self
    {
        return $this->filter(fn(Meta $meta) => 
            $meta->name === $className || is_a($meta->name, $className, true)
        );
    }

    /**
     * 获取所有元数据对象数组。
     * 
     * @return array<T> 元数据对象数组
     */
    public function all(): array
    {
        return $this->list;
    }

    /**
     * 合并两个元数据集合。
     * 
     * @param self<T> $other 要合并的另一个集合
     * @return self<T> 合并后的新集合
     */
    public function merge(self $other): self
    {
        return new self(array_merge($this->list, $other->all()));
    }

    /**
     * 获取集合中的元数据对象数量。
     * 
     * @return int 数量
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * 检查集合是否为空。
     * 
     * @return bool 是否为空
     */
    public function isEmpty(): bool
    {
        return count($this->list) === 0;
    }

    /**
     * 检查集合是否不为空。
     * 
     * @return bool 是否不为空
     */
    public function isNotEmpty(): bool
    {
        return count($this->list) > 0;
    }

    /**
     * 获取元数据对象的迭代器。
     * 
     * @return Traversable<int, T> 迭代器
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->list);
    }

    /**
     * 对每个元数据对象执行回调函数。
     * 
     * @param callable(T): void $fn 回调函数
     */
    public function each(callable $fn): void
    {
        foreach ($this->list as $meta) {
            $fn($meta);
        }
    }

    /**
     * 检查是否有元数据对象满足条件。
     * 
     * @param callable(T): bool $fn 条件回调函数
     * @return bool 是否存在满足条件的元素
     */
    public function some(callable $fn): bool
    {
        foreach ($this->list as $meta) {
            if ($fn($meta)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查是否所有元数据对象都满足条件。
     * 
     * @param callable(T): bool $fn 条件回调函数
     * @return bool 是否全部满足条件
     */
    public function every(callable $fn): bool
    {
        foreach ($this->list as $meta) {
            if (!$fn($meta)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取指定索引的元数据对象。
     * 
     * @param int $index 索引
     * @return T|null 元数据对象，不存在返回null
     */
    public function at(int $index): ?Meta
    {
        return $this->list[$index] ?? null;
    }

    /**
     * 获取所有属性实例。
     * 
     * @return array<object> 属性实例数组
     */
    public function getInstances(): array
    {
        return $this->map(fn(Meta $meta) => $meta->getInstance());
    }

    /**
     * 按属性名分组。
     * 
     * @return array<string, self<T>> 分组后的集合
     */
    public function groupByName(): array
    {
        $groups = [];
        foreach ($this->list as $meta) {
            $groups[$meta->name][] = $meta;
        }
        
        $result = [];
        foreach ($groups as $name => $metas) {
            $result[$name] = new self($metas);
        }
        
        return $result;
    }

    /**
     * 按条件分组。
     * 
     * @template K of array-key
     * @param callable(T): K $fn 分组键回调函数
     * @return array<K, self<T>> 分组后的集合
     */
    public function groupBy(callable $fn): array
    {
        $groups = [];
        foreach ($this->list as $meta) {
            $key = $fn($meta);
            $groups[$key][] = $meta;
        }
        
        $result = [];
        foreach ($groups as $key => $metas) {
            $result[$key] = new self($metas);
        }
        
        return $result;
    }

    /**
     * 排序元数据集合。
     * 
     * @param callable(T, T): int $comparator 比较函数
     * @return self<T> 排序后的新集合
     */
    public function sort(callable $comparator): self
    {
        $list = $this->list;
        usort($list, $comparator);
        return new self($list);
    }

    /**
     * 反转元数据集合顺序。
     * 
     * @return self<T> 反转后的新集合
     */
    public function reverse(): self
    {
        return new self(array_reverse($this->list));
    }

    /**
     * 获取指定数量的元数据对象。
     * 
     * @param int $limit 数量限制
     * @return self<T> 截取后的新集合
     */
    public function take(int $limit): self
    {
        return new self(array_slice($this->list, 0, $limit));
    }

    /**
     * 跳过指定数量的元数据对象。
     * 
     * @param int $offset 跳过数量
     * @return self<T> 跳过后的新集合
     */
    public function skip(int $offset): self
    {
        return new self(array_slice($this->list, $offset));
    }

    /**
     * 转换为数组表示。
     * 
     * @return array<int, array{ name: string, args: array, target: string }> 属性信息数组
     */
    public function toArray(): array
    {
        return $this->map(fn(Meta $meta) => $meta->toArray());
    }
}
