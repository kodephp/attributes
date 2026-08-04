<?php

declare(strict_types=1);

namespace Kode\Attributes;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * 属性元数据集合类。
 *
 * 提供便捷的属性集合操作，包括过滤、映射、遍历、分组、排序等功能。
 * 支持协变泛型，允许子类安全返回。
 *
 * @template-covariant T of Meta
 * @implements IteratorAggregate<int, T>
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class MetaList implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * 元数据对象列表。
     *
     * @var array<int, T>
     */
    private array $list;

    /**
     * 创建新的 MetaList 实例。
     *
     * @param array<T> $list 元数据对象数组
     */
    public function __construct(array $list = [])
    {
        $this->list = array_values($list);
    }

    /**
     * 创建空集合。
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * 由持久化快照还原集合。
     *
     * @param array<int, array{name: string, args?: array<int|string, mixed>}> $snapshot 快照数据
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self(array_map(static fn (array $item): Meta => Meta::fromArray($item), $snapshot));
    }

    /**
     * 获取列表中的第一个元数据对象。
     *
     * @return T|null 第一个元数据对象，空列表返回 null
     */
    public function first(): ?Meta
    {
        return $this->list[0] ?? null;
    }

    /**
     * 获取列表中的最后一个元数据对象。
     *
     * @return T|null 最后一个元数据对象，空列表返回 null
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
     * 反向过滤（剔除满足条件的元素）。
     *
     * @param callable(T): bool $fn 过滤回调函数
     * @return self<T> 过滤后的新集合
     */
    public function reject(callable $fn): self
    {
        return new self(array_filter($this->list, static fn (Meta $meta): bool => !$fn($meta)));
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
     * 归约集合。
     *
     * @template R
     * @param callable(R, T): R $fn 归约回调函数
     * @param R $initial 初始值
     * @return R 归约结果
     */
    public function reduce(callable $fn, mixed $initial = null): mixed
    {
        return array_reduce($this->list, $fn, $initial);
    }

    /**
     * 检查列表中是否包含指定类（或其子类）的属性。
     *
     * @param string $className 属性类名
     */
    public function has(string $className): bool
    {
        return $this->get($className) !== null;
    }

    /**
     * 获取列表中第一个指定类（或其子类）的属性元数据。
     *
     * @param string $className 属性类名
     * @return T|null 属性元数据，不存在返回 null
     */
    public function get(string $className): ?Meta
    {
        foreach ($this->list as $meta) {
            if ($meta->is($className)) {
                return $meta;
            }
        }

        return null;
    }

    /**
     * 获取列表中所有指定类（或其子类）的属性元数据。
     *
     * @param string $className 属性类名
     * @return self<T> 属性元数据集合
     */
    public function getAll(string $className): self
    {
        return $this->filter(static fn (Meta $meta): bool => $meta->is($className));
    }

    /**
     * 获取所有元数据对象数组。
     *
     * @return array<int, T> 元数据对象数组
     */
    public function all(): array
    {
        return $this->list;
    }

    /**
     * 获取所有属性类名。
     *
     * @return array<int, string> 属性类名数组
     */
    public function names(): array
    {
        return $this->map(static fn (Meta $meta): string => $meta->name);
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
     * 合并多个元数据集合。
     *
     * @param self<T> ...$others 要合并的集合
     * @return self<T> 合并后的新集合
     */
    public function concat(self ...$others): self
    {
        $list = $this->list;

        foreach ($others as $other) {
            $list = array_merge($list, $other->all());
        }

        return new self($list);
    }

    /**
     * 按属性类名去重（保留首次出现）。
     *
     * @return self<T> 去重后的新集合
     */
    public function unique(): self
    {
        $seen = [];
        $list = [];

        foreach ($this->list as $meta) {
            if (isset($seen[$meta->name])) {
                continue;
            }

            $seen[$meta->name] = true;
            $list[] = $meta;
        }

        return new self($list);
    }

    /**
     * 提取每个属性的指定参数值。
     *
     * @param string|int $argument 参数名或位置
     * @param mixed $default 默认值
     * @return array<int, mixed> 参数值数组
     */
    public function pluck(string|int $argument, mixed $default = null): array
    {
        return $this->map(static fn (Meta $meta): mixed => $meta->getArgument($argument, $default));
    }

    /**
     * 按参数值筛选。
     *
     * @param string|int $argument 参数名或位置
     * @param mixed $value 期望值
     * @return self<T> 筛选后的新集合
     */
    public function where(string|int $argument, mixed $value): self
    {
        return $this->filter(static fn (Meta $meta): bool => $meta->getArgument($argument) === $value);
    }

    /**
     * 获取集合中的元数据对象数量。
     */
    #[\Override]
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * 检查集合是否为空。
     */
    public function isEmpty(): bool
    {
        return $this->list === [];
    }

    /**
     * 检查集合是否不为空。
     */
    public function isNotEmpty(): bool
    {
        return $this->list !== [];
    }

    /**
     * 获取元数据对象的迭代器。
     *
     * @return Traversable<int, T> 迭代器
     */
    #[\Override]
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
     * 获取指定索引的元数据对象（支持负索引）。
     *
     * @param int $index 索引
     * @return T|null 元数据对象，不存在返回 null
     */
    public function at(int $index): ?Meta
    {
        if ($index < 0) {
            $index += count($this->list);
        }

        return $this->list[$index] ?? null;
    }

    /**
     * 获取所有属性实例。
     *
     * @return array<int, object> 属性实例数组
     */
    public function getInstances(): array
    {
        return $this->map(static fn (Meta $meta): object => $meta->getInstance());
    }

    /**
     * 获取所有属性实例（getInstances 的别名）。
     *
     * @return array<int, object> 属性实例数组
     */
    public function instances(): array
    {
        return $this->getInstances();
    }

    /**
     * 获取第一个属性实例。
     *
     * @param string|null $className 可选的属性类名过滤
     */
    public function firstInstance(?string $className = null): ?object
    {
        $meta = $className === null ? $this->first() : $this->get($className);

        return $meta?->getInstance();
    }

    /**
     * 仅保留能够成功实例化的属性实例（忽略实例化失败项）。
     *
     * @return array<int, object> 属性实例数组
     */
    public function safeInstances(): array
    {
        $instances = [];

        foreach ($this->list as $meta) {
            $instance = $meta->tryInstance();

            if ($instance !== null) {
                $instances[] = $instance;
            }
        }

        return $instances;
    }

    /**
     * 按属性名分组。
     *
     * @return array<string, self<T>> 分组后的集合
     */
    public function groupByName(): array
    {
        return $this->groupBy(static fn (Meta $meta): string => $meta->name);
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
            $groups[$fn($meta)][] = $meta;
        }

        return array_map(static fn (array $metas): self => new self($metas), $groups);
    }

    /**
     * 以属性名为键建立索引（同名保留首个）。
     *
     * @return array<string, T> 索引数组
     */
    public function keyByName(): array
    {
        $indexed = [];

        foreach ($this->list as $meta) {
            $indexed[$meta->name] ??= $meta;
        }

        return $indexed;
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
     * 按属性参数排序。
     *
     * @param string|int $argument 参数名或位置
     * @param bool $descending 是否降序
     * @return self<T> 排序后的新集合
     */
    public function sortByArgument(string|int $argument, bool $descending = false): self
    {
        return $this->sort(static function (Meta $a, Meta $b) use ($argument, $descending): int {
            $left = $a->getArgument($argument);
            $right = $b->getArgument($argument);

            return $descending ? ($right <=> $left) : ($left <=> $right);
        });
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
     * @return array<int, array<string, mixed>> 属性信息数组
     */
    public function toArray(): array
    {
        return $this->map(static fn (Meta $meta): array => $meta->toArray());
    }

    /**
     * 导出可持久化的最小快照。
     *
     * @return array<int, array{name: string, args: array<int|string, mixed>}> 快照数据
     */
    public function toSnapshot(): array
    {
        return $this->map(static fn (Meta $meta): array => $meta->toSnapshot());
    }

    /**
     * JSON 序列化表示。
     *
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
