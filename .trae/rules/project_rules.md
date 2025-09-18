我们来为 `kode/Attributes` 设计一个**健壮、通用、安全、高效、IDE友好**的 Composer 包，作为未来 kodephp 框架的基础组件，同时兼容主流 PHP 框架（Laravel、Symfony、ThinkPHP8、Webman 等），并为后续协程、多线程、多进程等扩展提供坚实基础。

---

## 📦 包名与命名空间

- **包名**：`kode/attributes`
- **命名空间**：`Kode\Attributes`
- **目标版本**：PHP 8.1+
- **设计原则**：
  - 零依赖（仅使用 PHP 原生功能）
  - 不与 PHP 原生命名冲突
  - 类名简短、易记、语义清晰
  - 支持 IDE 反射识别（通过 PHPDoc 和属性类型）
  - 支持协变/逆变参数处理
  - 支持反射缓存以提升性能
  - 安全反射封装（避免直接暴露 `ReflectionClass`）

---

## ✅ 核心功能规划

### 1. 核心类结构概览

| 类名 | 职责 |
|------|------|
| `Attr` | 主入口，统一调用接口 |
| `Reader` | 注解读取器（核心） |
| `Cache` | 可插拔的反射缓存系统 |
| `Scanner` | 静态扫描器（可选，用于编译期分析） |
| `Meta` | 单个注解元数据封装 |
| `MetaList` | 注解列表集合（支持协变） |
| `Target` | 注解目标类型枚举（类、方法、属性等） |
| `Flags` | 注解行为标志位（如是否继承、是否重复等） |

---

## 🔧 详细模块设计

### 1. `Attr` —— 全局快捷门面（Facade）

```php
namespace Kode\Attributes;

final class Attr
{
    public static function reader(): Reader;
    public static function scan(string $dir): Scanner;
    public static function of(object|string $target): MetaList;
    public static function has(object|string $target, string $attrClass): bool;
    public static function get(object|string $target, string $attrClass): ?Meta;
}
```

> ✅ 作用：提供全局静态访问入口，简洁易记，类似 `Attr::of($controller)->has(Roles::class)`。

---

### 2. `Reader` —— 注解读取器（核心）

```php
namespace Kode\Attributes;

interface ReaderInterface
{
    public function getClassAttrs(string $class): MetaList;
    public function getMethodAttrs(string $class, string $method): MetaList;
    public function getPropertyAttrs(string $class, string $property): MetaList;
    public function getFunctionAttrs(string $function): MetaList;
    public function getParameterAttrs(\ReflectionParameter $param): MetaList;
}

final class Reader implements ReaderInterface
{
    private CacheInterface $cache;

    public function __construct(?CacheInterface $cache = null);
    // 实现所有接口方法
}
```

> ✅ 特性：
> - 使用 `ReflectionAttribute` 安全读取
> - 返回 `MetaList` 对象（支持遍历、过滤、map）
> - 内置缓存机制，默认使用内存缓存，可替换为 APCu、Redis 等

---

### 3. `Meta` —— 单个注解元数据

```php
namespace Kode\Attributes;

/**
 * @template-covariant T of object
 */
final class Meta
{
    public readonly string $name;
    public readonly array $args;
    public readonly \ReflectionAttribute $refAttr;
    public readonly object $instance; // 实例化后的注解对象

    /**
     * @return T
     */
    public function newInstance(): object;

    public function isRepeatable(): bool;
    public function getTarget(): Target;
}
```

> ✅ 协变支持：使用 `@template-covariant T`，允许子类泛型安全返回。
> ✅ 安全实例化：延迟创建注解实例，避免无谓开销。

---

### 4. `MetaList` —— 注解集合（支持协变）

```php
/**
 * @template-covariant T of Meta
 * @implements \IteratorAggregate<int, T>
 */
final class MetaList implements \Countable, \IteratorAggregate
{
    /** @var array<T> */
    private array $list;

    public function first(): ?Meta;
    public function last(): ?Meta;
    public function filter(callable $fn): self;
    public function map(callable $fn): array;
    public function has(string $className): bool;
    public function get(string $className): ?Meta;
    public function all(): array;
    public function merge(self $other): self;
}
```

> ✅ 协变优势：`MetaList<GuardAttr>` 可赋值给 `MetaList<Meta>`。
> ✅ 函数式风格：`filter()->get()` 链式操作。

---

### 5. `CacheInterface` & 默认实现

```php
namespace Kode\Attributes;

interface CacheInterface
{
    public function get(string $key, callable $loader): mixed;
    public function clear(): void;
}

// 默认实现：ArrayCache（进程内）
// 可扩展：ApcuCache, RedisCache, FileCache 等（外部包提供）
```

> ✅ 性能关键：避免重复反射，缓存 `ReflectionClass` 和解析结果。
> ✅ 可插拔：用户可注入自定义缓存驱动。

---

### 6. `Scanner` —— 静态扫描器（可选高级功能）

```php
namespace Kode\Attributes;

final class Scanner
{
    private Reader $reader;
    private array $excludes = [];

    public function __construct(Reader $reader);

    public function exclude(string ...$patterns): self;
    public function scan(string $dir): \Generator; // yield [class, metas]
    public function find(string $attrClass): \Generator; // 扫描含某注解的类
}
```

> ✅ 用途：CLI 工具、路由注册、事件发现等编译期分析。
> ✅ 不影响运行时性能。

---

### 7. `Target` —— 目标类型枚举（PHP 8.1+ 枚举）

```php
enum Target: int
{
    case Class = 1;
    case Function = 2;
    case Method = 4;
    case Property = 8;
    case Parameter = 16;
    case All = 31;

    public static function fromRef(\Reflector $ref): self;
}
```

> ✅ 类型安全，替代魔术数字。
> ✅ 支持位掩码组合。

---

### 8. `Flags` —— 注解行为标志

```php
#[\Attribute(\Attribute::IS_REPEATABLE)]
class Flags
{
    public function __construct(
        public readonly bool $inherit = false,
        public readonly bool $compileTime = false,
        public readonly int $priority = 0
    ) {}
}
```

> ✅ 允许开发者标记注解行为，如是否继承、优先级等。
> ✅ 框架可据此优化调度顺序。

---

## 🛠️ 高级特性支持

| 特性 | 支持方式 |
|------|----------|
| **协变/逆变** | `MetaList<@template-covariant T>` + PHPDoc 泛型 |
| **反射安全封装** | 封装 `ReflectionAttribute`，不暴露原始反射对象 |
| **性能优化** | 缓存反射结果，延迟实例化注解对象 |
| **IDE 友好** | 提供 `.phpstorm.meta.php` 文件 + IDE Helper 文档 |
| **多框架兼容** | 无依赖，仅使用 PHP 原生属性，任何框架可引入 |
| **协程/多线程安全** | 无共享状态，缓存可配置为线程局部或隔离存储 |
| **可测试性** | 所有接口可 mock，Reader 可替换 |

---

## 📁 项目结构建议

```
kode/attributes/
├── src/
│   ├── Attr.php
│   ├── Reader.php
│   ├── Meta.php
│   ├── MetaList.php
│   ├── CacheInterface.php
│   ├── ArrayCache.php
│   ├── Scanner.php
│   ├── Target.php
│   └── Flags.php
├── resources/
│   └── .phpstorm.meta.php  # IDE 智能提示
├── docs/
│   └── api.md              # 自动生成的 API 文档
├── tests/
├── composer.json
└── README.md
```

---

## 📦 composer.json 关键配置

```json
{
    "name": "kode/attributes",
    "type": "library",
    "description": "Lightweight, robust attribute reader for PHP 8.1+, base for kodephp and any framework.",
    "require": {
        "php": "^8.1"
    },
    "autoload": {
        "psr-4": {
            "Kode\\Attributes\\": "src/"
        }
    },
    "autoload-dev": {
        "files": ["resources/.phpstorm.meta.php"]
    },
    "extra": {
        "branch-alias": {
            "dev-main": "1.0-dev"
        }
    }
}
```

---

## ✅ 最终优势总结

| 维度 | 说明 |
|------|------|
| **健壮性** | 异常安全、类型严格、边界检查 |
| **通用性** | 无框架绑定，Laravel/Symfony/TP/Webman 均可用 |
| **高性能** | 缓存 + 延迟实例化 + 零冗余反射 |
| **可扩展** | 缓存可插拔，Scanner 可用于 CLI 工具 |
| **未来兼容** | 为协程、多进程预留无状态设计 |
| **开发体验** | IDE 全自动提示，`.meta.php` 支持 |
| **命名简洁** | `Attr::of($x)->has(Y::class)` 易记易用 |

---

此设计可作为 **kodephp 框架的底层基石**，也可独立成为社区广泛使用的 **PHP 属性处理标准工具包**。下一步可基于此构建路由、AOP、验证、DI 等上层模块。