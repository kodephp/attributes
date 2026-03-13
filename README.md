# kode/attributes

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF)](https://php.net/)
[![License](https://img.shields.io/badge/License-Apache%202.0-green)](LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/kode/attributes)](https://packagist.org/packages/kode/attributes)

一个轻量级、健壮的 PHP 8.1+ 属性（Attribute）读取器，为 kodephp 框架和主流 PHP 框架（Laravel、Symfony、ThinkPHP8、Webman 等）提供基础组件支持。

## 特性

- **零依赖** - 仅使用 PHP 原生功能，无第三方依赖
- **高性能** - 内置反射缓存机制，延迟实例化属性对象
- **类型安全** - 利用 PHP 8.1+ 的枚举和泛型特性
- **IDE 友好** - 提供完整的 PHPStorm 元数据支持
- **协变支持** - `MetaList<@template-covariant T>` 支持类型安全的协变
- **框架无关** - 可在任何 PHP 8.1+ 项目中使用
- **安全封装** - 封装反射 API，避免直接暴露 `ReflectionClass`
- **可扩展** - 可插拔缓存系统，支持自定义缓存驱动

## 安装

```bash
composer require kode/attributes
```

## 快速开始

### 基本用法

```php
use Kode\Attributes\Attr;

// 检查类是否具有特定属性
if (Attr::has(MyClass::class, MyAttribute::class)) {
    // 获取属性实例
    $meta = Attr::get(MyClass::class, MyAttribute::class);
    $instance = $meta->getInstance();
}

// 获取类的所有属性
$attributes = Attr::of(MyClass::class);
foreach ($attributes as $meta) {
    echo $meta->name; // 属性类名
    print_r($meta->args); // 属性参数
}
```

### 高级用法

```php
use Kode\Attributes\Reader;
use Kode\Attributes\ArrayCache;

// 创建带自定义缓存的读取器
$cache = new ArrayCache();
$reader = new Reader($cache);

// 获取类属性
$classAttrs = $reader->getClassAttrs(MyClass::class);

// 获取方法属性
$methodAttrs = $reader->getMethodAttrs(MyClass::class, 'myMethod');

// 获取属性属性
$propertyAttrs = $reader->getPropertyAttrs(MyClass::class, 'myProperty');

// 获取缓存统计
$stats = $cache->getStats();
echo "缓存命中率: " . ($stats['hitRate'] * 100) . "%";
```

### 过滤与映射

```php
use Kode\Attributes\Attr;

// 过滤属性
$filtered = Attr::of(MyClass::class)
    ->filter(fn($meta) => is_subclass_of($meta->name, BaseAttribute::class));

// 映射属性为实例
$instances = Attr::of(MyClass::class)
    ->map(fn($meta) => $meta->getInstance());

// 获取所有指定类型的属性
$allRoutes = Attr::getAll(MyClass::class, Route::class);

// 分组
$grouped = Attr::of(MyClass::class)->groupByName();
```

## 实际示例

### 路由定义

```php
use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET',
        public readonly string $name = ''
    ) {}
}

#[Route('/api/users', 'GET')]
#[Route('/api/users', 'POST')]
class UserController
{
    #[Route('/api/users/{id}', 'GET')]
    public function getUser(int $id): void
    {
        // 方法实现
    }
}

// 读取路由属性
$routes = Attr::of(UserController::class);

// 过滤 POST 路由
$postRoutes = $routes->filter(fn($meta) => $meta->getInstance()->method === 'POST');

// 获取所有路径
$paths = $routes->map(fn($meta) => $meta->getInstance()->path);
```

### 权限控制

```php
use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Role
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0
    ) {}
}

#[Role('admin', 100)]
#[Role('user', 50)]
class AdminController
{
    #[Role('super-admin', 200)]
    public function dangerousAction(): void
    {
        // 需要超级管理员权限
    }
}

// 检查权限
$roles = Attr::of(AdminController::class)
    ->filter(fn($meta) => $meta->name === Role::class)
    ->map(fn($meta) => $meta->getInstance()->name);

// 按优先级排序
$sortedRoles = Attr::of(AdminController::class)
    ->filter(fn($meta) => $meta->name === Role::class)
    ->sort(fn($a, $b) => $b->getInstance()->priority <=> $a->getInstance()->priority);
```

### 目录扫描

```php
use Kode\Attributes\Attr;

// 创建扫描器
$scanner = Attr::scan(__DIR__ . '/src');

// 排除特定目录
$scanner->exclude('vendor', 'tests', '.git');

// 查找所有带 Route 属性的类
foreach ($scanner->find(__DIR__ . '/src', Route::class) as $class => $metas) {
    echo "发现路由类: $class\n";
    foreach ($metas as $meta) {
        $route = $meta->getInstance();
        echo "  - {$route->method} {$route->path}\n";
    }
}

// 深度扫描（包含方法、属性）
foreach ($scanner->scanDeep(__DIR__ . '/src') as $class => $info) {
    echo "类: $class\n";
    echo "  类属性: " . count($info['class']) . "\n";
    echo "  方法属性: " . count($info['methods']) . "\n";
    echo "  属性属性: " . count($info['properties']) . "\n";
}
```

## API 参考

### 主要类

| 类名 | 职责 |
|------|------|
| `Attr` | 全局门面，提供静态访问入口 |
| `Reader` | 属性读取器核心实现 |
| `Meta` | 单个属性元数据封装 |
| `MetaList` | 属性集合，支持链式操作 |
| `ArrayCache` | 内存缓存实现 |
| `Scanner` | 目录扫描器 |
| `Target` | 属性目标类型枚举 |
| `Flags` | 属性行为标志 |

### 关键接口

| 接口名 | 职责 |
|--------|------|
| `ReaderInterface` | 属性读取器契约 |
| `CacheInterface` | 缓存驱动契约 |

### Attr 门面

```php
// 获取 Reader 实例
Attr::reader(): Reader

// 设置自定义 Reader
Attr::setReader(Reader $reader): void

// 获取目标的所有属性
Attr::of(object|string $target): MetaList

// 检查是否存在属性
Attr::has(object|string $target, string $attrClass): bool

// 获取单个属性
Attr::get(object|string $target, string $attrClass): ?Meta

// 获取所有指定类型属性
Attr::getAll(object|string $target, string $attrClass): MetaList

// 创建扫描器
Attr::scan(string $dir): Scanner

// 清除缓存
Attr::clear(): void
```

### MetaList 集合方法

```php
// 获取元素
$metaList->first(): ?Meta
$metaList->last(): ?Meta
$metaList->at(int $index): ?Meta
$metaList->all(): array

// 查询
$metaList->has(string $className): bool
$metaList->get(string $className): ?Meta
$metaList->getAll(string $className): MetaList
$metaList->count(): int
$metaList->isEmpty(): bool
$metaList->isNotEmpty(): bool

// 操作
$metaList->filter(callable $fn): MetaList
$metaList->map(callable $fn): array
$metaList->flatMap(callable $fn): array
$metaList->each(callable $fn): void
$metaList->some(callable $fn): bool
$metaList->every(callable $fn): bool

// 排序与分页
$metaList->sort(callable $comparator): MetaList
$metaList->reverse(): MetaList
$metaList->take(int $limit): MetaList
$metaList->skip(int $offset): MetaList

// 分组
$metaList->groupByName(): array
$metaList->groupBy(callable $fn): array

// 合并
$metaList->merge(MetaList $other): MetaList
```

### Meta 元数据方法

```php
// 基本信息
$meta->name: string           // 属性类名
$meta->args: array            // 属性参数
$meta->reflector: ?\Reflector // 所属反射对象

// 实例化
$meta->getInstance(): object
$meta->newInstance(): object

// 目标信息
$meta->getTarget(): Target
$meta->supportsTarget(Target $target): bool
$meta->isRepeatable(): bool

// 参数访问
$meta->getArgument(string $name, mixed $default = null): mixed
$meta->getNamedArguments(): array
$meta->getPositionalArguments(): array

// 其他
$meta->getDeclaringClass(): ?string
$meta->toArray(): array
```

### Target 枚举

```php
use Kode\Attributes\Target;

// 枚举值
Target::Clazz         // 类
Target::Function      // 函数
Target::Method        // 方法
Target::Property      // 属性
Target::ClassConstant // 类常量
Target::Parameter     // 参数
Target::All           // 全部

// 静态方法
Target::fromRef(\Reflector $ref): Target
Target::fromAttributeFlags(int $flags): Target

// 实例方法
$target->supports(Target $other): bool
$target->combine(Target ...$others): Target
$target->getTargets(): array
$target->toAttributeFlags(): int
$target->getLabel(): string      // 中文标签
$target->toString(): string
```

### Flags 标志类

```php
use Kode\Attributes\Flags;

// 创建实例
new Flags(
    inherit: bool,      // 是否继承
    compileTime: bool,  // 是否编译期处理
    priority: int,      // 优先级
    cacheable: bool     // 是否可缓存
)

// 静态工厂方法
Flags::inherit(int $priority = 0): Flags
Flags::compileTime(int $priority = 0): Flags
Flags::highPriority(int $priority = 100): Flags
Flags::nonCacheable(): Flags

// 实例方法
$flags->isDefault(): bool
$flags->merge(Flags $other): Flags
$flags->toArray(): array
$flags->__toString(): string
```

## 自定义缓存驱动

```php
use Kode\Attributes\CacheInterface;

class RedisCache implements CacheInterface
{
    private \Redis $redis;
    
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }
    
    public function get(string $key, callable $loader): mixed
    {
        $value = $this->redis->get($key);
        
        if ($value === false) {
            $value = $loader();
            $this->redis->set($key, serialize($value));
        }
        
        return is_string($value) ? unserialize($value) : $value;
    }
    
    public function has(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }
    
    public function set(string $key, mixed $value): void
    {
        $this->redis->set($key, serialize($value));
    }
    
    public function delete(string $key): void
    {
        $this->redis->del($key);
    }
    
    public function clear(): void
    {
        $this->redis->flushDB();
    }
}

// 使用自定义缓存
$reader = new Reader(new RedisCache($redis));
Attr::setReader($reader);
```

## 系统要求

- PHP >= 8.1
- ext-json
- ext-mbstring

## 兼容性

| PHP 版本 | 支持状态 |
|----------|----------|
| PHP 8.1  | ✅ 完全支持 |
| PHP 8.2  | ✅ 完全支持 |
| PHP 8.3  | ✅ 完全支持 |
| PHP 8.4  | ✅ 完全支持 |
| PHP 8.5  | ✅ 完全支持（优先使用新特性） |

## 框架兼容性

| 框架 | 支持状态 |
|------|----------|
| Laravel | ✅ 完全兼容 |
| Symfony | ✅ 完全兼容 |
| ThinkPHP 8 | ✅ 完全兼容 |
| Webman | ✅ 完全兼容 |
| Hyperf | ✅ 完全兼容 |
| 原生 PHP | ✅ 完全兼容 |

## 测试

```bash
# 运行测试
composer test

# 生成覆盖率报告
composer test:coverage

# 代码风格检查
composer check

# 代码风格修复
composer fix
```

## 贡献

欢迎提交 Issue 和 Pull Request 来改进这个项目。

### 贡献指南

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 创建 Pull Request

## 许可证

本项目基于 [Apache License 2.0](LICENSE) 开源协议发布。

## 作者

KodePHP - [382601296@qq.com](mailto:382601296@qq.com)

## 更新日志

### v1.2.2 (2025-03-13)

- 完善 Target 枚举类中文注释
- 新增 `Target::toAttributeFlags()` 方法
- 新增 `Target::getLabel()` 方法获取中文标签
- 新增 `Target::toString()` 方法
- 优化 composer.json 配置，添加脚本和支持信息

### v1.2.1 (2025-03-12)

- 移除冗余的性能优化建议章节（功能已内置）
- 精简 README 文档结构

### v1.2.0 (2025-03-12)

- 重构核心类，优化代码结构
- 完善所有类的中文注释
- 新增 `Reader::getAllMethodAttrs()` 和 `Reader::getAllPropertyAttrs()` 方法
- 新增 `Reader::getConstantAttrs()` 方法支持类常量属性
- 新增 `ArrayCache` 统计功能（命中率、缓存大小等）
- 新增 `Meta::getDeclaringClass()` 方法
- 新增 `Meta::toArray()` 方法
- 新增 `MetaList` 丰富的集合操作方法
- 新增 `Target::ClassConstant` 枚举值
- 新增 `Flags::$cacheable` 属性
- 优化 `Scanner` 扫描性能
- 完善单元测试覆盖（69个测试用例）
- 整合文档到 README.md（中文）
- 删除冗余的 docs 目录

### v1.0.0 (2024-01-01)

- 初始版本发布
- 核心属性读取功能
- 缓存系统
- 目录扫描器

## 致谢

感谢所有为这个项目做出贡献的开发者！