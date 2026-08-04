<?php

declare(strict_types=1);

namespace Kode\Attributes;

use Attribute;
use JsonSerializable;
use Kode\Attributes\Exception\AttributeInstantiationException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;
use Stringable;
use Throwable;

/**
 * 属性元数据封装类。
 *
 * 封装 ReflectionAttribute 实例，提供便捷的属性信息访问与实例化功能。
 * 同时支持"脱离反射"的纯数据构造（名称 + 参数），
 * 使元数据可以被序列化后写入 Redis / 文件等持久化缓存
 * （ReflectionAttribute 本身禁止序列化）。
 *
 * @template-covariant T of object
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class Meta implements JsonSerializable, Stringable
{
    /**
     * 属性类的元信息静态缓存。
     *
     * @var array<string, array{flags: int, repeatable: bool}>
     */
    private static array $attributeMeta = [];

    /**
     * 属性构造函数参数位置静态缓存。
     *
     * @var array<string, array<string, int>>
     */
    private static array $constructorPositions = [];

    /**
     * 反射属性实例；纯数据构造时为 null。
     */
    public readonly ?ReflectionAttribute $refAttr;

    /**
     * 所属反射对象（可选）。
     */
    public readonly ?Reflector $reflector;

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
     * 目标集合缓存。
     */
    private ?TargetSet $targetSet = null;

    /**
     * 创建新的 Meta 实例。
     *
     * @param ReflectionAttribute|string $attribute 反射属性实例，或属性类名（纯数据模式）
     * @param Reflector|null $reflector 所属反射对象
     * @param array<int|string, mixed> $args 纯数据模式下的属性参数
     */
    public function __construct(
        ReflectionAttribute|string $attribute,
        ?Reflector $reflector = null,
        array $args = []
    ) {
        if ($attribute instanceof ReflectionAttribute) {
            $this->refAttr = $attribute;
            $this->name = $attribute->getName();
            $this->args = $attribute->getArguments();
        } else {
            $this->refAttr = null;
            $this->name = ltrim($attribute, '\\');
            $this->args = $args;
        }

        $this->reflector = $reflector;
    }

    /**
     * 从纯数据创建 Meta 实例（可用于反序列化持久化缓存）。
     *
     * @param array{name: string, args?: array<int|string, mixed>} $data 数据数组
     */
    public static function fromArray(array $data): self
    {
        return new self($data['name'], null, $data['args'] ?? []);
    }

    /**
     * 是否由反射读取而来（false 表示纯数据还原）。
     */
    public function isReflected(): bool
    {
        return $this->refAttr !== null;
    }

    /**
     * 获取实例化后的属性对象（带缓存）。
     *
     * @return T 属性实例
     * @throws AttributeInstantiationException 当属性实例化失败时抛出
     */
    public function getInstance(): object
    {
        return $this->instance ??= $this->build();
    }

    /**
     * 创建一个全新的属性实例（不使用缓存）。
     *
     * @return T 属性实例
     * @throws AttributeInstantiationException 当属性实例化失败时抛出
     */
    public function newInstance(): object
    {
        return $this->build();
    }

    /**
     * 尝试实例化属性，失败时返回 null 而不抛异常。
     *
     * @return T|null 属性实例
     */
    public function tryInstance(): ?object
    {
        try {
            return $this->getInstance();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 判断该属性是否为指定类（或其子类）。
     *
     * @param string $className 属性类名
     */
    public function is(string $className): bool
    {
        $className = ltrim($className, '\\');

        return $this->name === $className || is_a($this->name, $className, true);
    }

    /**
     * 检查属性是否可重复。
     */
    public function isRepeatable(): bool
    {
        return self::attributeMetaOf($this->name)['repeatable'];
    }

    /**
     * 获取属性声明的原始 Attribute 标志位。
     */
    public function getFlags(): int
    {
        return self::attributeMetaOf($this->name)['flags'];
    }

    /**
     * 获取属性允许的目标集合（精确位掩码，不会退化成 All）。
     */
    public function getTargetSet(): TargetSet
    {
        return $this->targetSet ??= new TargetSet($this->getFlags());
    }

    /**
     * 获取属性的目标类型。
     *
     * 注意：组合目标（如 类|方法）无法用单个枚举精确表达，会返回 `Target::All`；
     * 需要精确判断请使用 {@see self::getTargetSet()} 或 {@see self::supportsTarget()}。
     */
    public function getTarget(): Target
    {
        return $this->getTargetSet()->toTarget();
    }

    /**
     * 检查属性是否支持指定目标。
     *
     * @param Target $target 要检查的目标类型
     */
    public function supportsTarget(Target $target): bool
    {
        return $this->getTargetSet()->has($target);
    }

    /**
     * 获取属性实际所处的目标种类（由 reflector 推断）。
     */
    public function getTargetKind(): ?Target
    {
        return $this->reflector === null ? null : Target::fromRef($this->reflector);
    }

    /**
     * 校验属性的声明目标与其实际所处位置是否匹配。
     *
     * 无 reflector 上下文时返回 true。
     */
    public function isValidPlacement(): bool
    {
        $kind = $this->getTargetKind();

        return $kind === null || $this->getTargetSet()->has($kind);
    }

    /**
     * 获取属性参数中的命名参数。
     *
     * @return array<string, mixed> 命名参数数组
     */
    public function getNamedArguments(): array
    {
        return array_filter($this->args, static fn (int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * 获取属性参数中的位置参数。
     *
     * @return array<int, mixed> 位置参数数组
     */
    public function getPositionalArguments(): array
    {
        return array_values(array_filter($this->args, static fn (int|string $key): bool => is_int($key), ARRAY_FILTER_USE_KEY));
    }

    /**
     * 是否存在指定参数。
     *
     * @param string|int $name 参数名或位置
     */
    public function hasArgument(string|int $name): bool
    {
        return array_key_exists($name, $this->args);
    }

    /**
     * 获取指定名称（或位置）的参数值。
     *
     * 命名参数未命中时，会尝试按构造函数签名回退到对应的位置参数，
     * 这样 `#[Route('/a')]` 与 `#[Route(path: '/a')]` 行为一致。
     *
     * @param string|int $name 参数名或位置
     * @param mixed $default 默认值
     */
    public function getArgument(string|int $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, $this->args)) {
            return $this->args[$name];
        }

        if (is_string($name)) {
            $position = self::constructorPosition($this->name, $name);

            if ($position !== null && array_key_exists($position, $this->args)) {
                return $this->args[$position];
            }
        }

        return $default;
    }

    /**
     * 获取属性所属的类名（如果适用）。
     */
    public function getDeclaringClass(): ?string
    {
        return match (true) {
            $this->reflector instanceof ReflectionObject => $this->reflector->getName(),
            $this->reflector instanceof ReflectionClass => $this->reflector->getName(),
            $this->reflector instanceof ReflectionMethod,
            $this->reflector instanceof ReflectionProperty,
            $this->reflector instanceof ReflectionClassConstant => $this->reflector->getDeclaringClass()->getName(),
            $this->reflector instanceof ReflectionParameter => $this->reflector->getDeclaringClass()?->getName(),
            default => null,
        };
    }

    /**
     * 获取属性所依附成员的名称（方法名 / 属性名 / 常量名 / 参数名 / 类名）。
     */
    public function getSubjectName(): ?string
    {
        return match (true) {
            $this->reflector instanceof ReflectionClass => $this->reflector->getName(),
            $this->reflector instanceof ReflectionMethod,
            $this->reflector instanceof ReflectionProperty,
            $this->reflector instanceof ReflectionClassConstant,
            $this->reflector instanceof ReflectionFunction,
            $this->reflector instanceof ReflectionParameter => $this->reflector->getName(),
            default => null,
        };
    }

    /**
     * 声明唯一标识：声明主体 + 属性类。
     *
     * 用于继承合并时按“声明位置”去重——父类与子类分别声明同名（且不可重复）属性时，
     * 二者主体不同、uid 不同，应同时保留；而同一声明被多次遍历（如接口/特性别名）
     * 时 uid 相同，应去重。纯数据模式（无 reflector）主体记为 global。
     */
    public function uid(): string
    {
        return ($this->getSubjectName() ?? 'global') . '|' . $this->name;
    }

    /**
     * 获取目标的人类可读描述。
     */
    public function describeSubject(): string
    {
        return TargetRef::describe($this->reflector);
    }

    /**
     * 转换为数组表示。
     *
     * @return array{name: string, args: array<int|string, mixed>, target: string, targets: array<int, string>, subject: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'args' => $this->args,
            'target' => $this->getTarget()->name,
            'targets' => array_map(static fn (Target $t): string => $t->name, $this->getTargetSet()->targets()),
            'subject' => $this->getSubjectName(),
        ];
    }

    /**
     * 导出可持久化的最小快照（仅名称与参数）。
     *
     * @return array{name: string, args: array<int|string, mixed>}
     */
    public function toSnapshot(): array
    {
        return ['name' => $this->name, 'args' => $this->args];
    }

    /**
     * JSON 序列化表示。
     *
     * @return array{name: string, args: array<int|string, mixed>, target: string, targets: array<int, string>, subject: string|null}
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 序列化时丢弃不可序列化的反射对象。
     *
     * @return array{name: string, args: array<int|string, mixed>}
     */
    /**
     * 原生序列化：保留已实例化的属性对象。
     *
     * 这样通过共享缓存（RedisCache 等，跨进程/分布式）取回的 Meta 仍能直接
     * 返回实例，无需再次反射构造。`toSnapshot()` 仍保持「最小快照」（不含实例），
     * 用于 JSON / 持久化场景；此处刻意保留实例以服务运行时缓存。
     *
     * @return array{name: string, args: array<int|string, mixed>, instance: ?object}
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'args' => $this->args,
            'instance' => $this->instance,
        ];
    }

    /**
     * 从原生序列化数据还原（纯数据模式，无反射对象）。
     *
     * @param array{name: string, args?: array<int|string, mixed>, instance?: ?object} $data 序列化数据
     */
    public function __unserialize(array $data): void
    {
        $this->refAttr = null;
        $this->reflector = null;
        $this->name = $data['name'];
        $this->args = $data['args'] ?? [];
        $this->instance = $data['instance'] ?? null;
        $this->targetSet = null;
    }

    /**
     * 字符串表示，例如 `#[App\Route('/users')]`。
     */
    #[\Override]
    public function __toString(): string
    {
        $parts = [];

        foreach ($this->args as $key => $value) {
            $rendered = self::renderValue($value);
            $parts[] = is_string($key) ? "{$key}: {$rendered}" : $rendered;
        }

        return $parts === []
            ? sprintf('#[%s]', $this->name)
            : sprintf('#[%s(%s)]', $this->name, implode(', ', $parts));
    }

    /**
     * 清空属性类元信息静态缓存（主要用于测试）。
     */
    public static function clearStaticCache(): void
    {
        self::$attributeMeta = [];
        self::$constructorPositions = [];
    }

    /**
     * 实例化属性对象。
     *
     * @return T 属性实例
     * @throws AttributeInstantiationException 实例化失败
     */
    private function build(): object
    {
        try {
            if ($this->refAttr !== null) {
                return $this->refAttr->newInstance();
            }

            if (!class_exists($this->name)) {
                throw new \LogicException(sprintf('属性类 "%s" 不存在。', $this->name));
            }

            /** @var T */
            return new $this->name(...$this->args);
        } catch (Throwable $e) {
            throw AttributeInstantiationException::forAttribute(
                $this->name,
                $e,
                $this->reflector !== null ? TargetRef::describe($this->reflector) : null
            );
        }
    }

    /**
     * 读取属性类的 #[Attribute] 元信息（带静态缓存）。
     *
     * @return array{flags: int, repeatable: bool}
     */
    private static function attributeMetaOf(string $attributeClass): array
    {
        if (isset(self::$attributeMeta[$attributeClass])) {
            return self::$attributeMeta[$attributeClass];
        }

        $flags = Attribute::TARGET_ALL;
        $repeatable = false;

        try {
            if (class_exists($attributeClass)) {
                $declarations = (new ReflectionClass($attributeClass))->getAttributes(Attribute::class);

                if ($declarations !== []) {
                    $args = $declarations[0]->getArguments();
                    $flags = (int) ($args['flags'] ?? $args[0] ?? Attribute::TARGET_ALL);
                    $repeatable = ($flags & Attribute::IS_REPEATABLE) === Attribute::IS_REPEATABLE;
                }
            }
        } catch (Throwable) {
            $flags = Attribute::TARGET_ALL;
            $repeatable = false;
        }

        return self::$attributeMeta[$attributeClass] = [
            'flags' => $flags,
            'repeatable' => $repeatable,
        ];
    }

    /**
     * 查询属性构造函数中某个参数的位置。
     */
    private static function constructorPosition(string $attributeClass, string $argument): ?int
    {
        if (!isset(self::$constructorPositions[$attributeClass])) {
            $positions = [];

            try {
                if (class_exists($attributeClass)) {
                    $constructor = (new ReflectionClass($attributeClass))->getConstructor();

                    foreach ($constructor?->getParameters() ?? [] as $parameter) {
                        $positions[$parameter->getName()] = $parameter->getPosition();
                    }
                }
            } catch (Throwable) {
                $positions = [];
            }

            self::$constructorPositions[$attributeClass] = $positions;
        }

        return self::$constructorPositions[$attributeClass][$argument] ?? null;
    }

    /**
     * 渲染参数值为可读字符串。
     */
    private static function renderValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => "'" . $value . "'",
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_int($value), is_float($value) => (string) $value,
            is_array($value) => '[...]',
            is_object($value) => $value::class,
            default => get_debug_type($value),
        };
    }
}
