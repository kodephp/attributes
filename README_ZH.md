# kode/attributes

一个轻量级、健壮的PHP 8.1+属性(Attribute)读取器，为kodephp框架和其他主流PHP框架(Laravel、Symfony、ThinkPHP8、Webman等)提供基础组件支持。

## 特性

- **零依赖** - 仅使用PHP原生功能
- **高性能** - 内置反射缓存机制
- **类型安全** - 利用PHP 8.1+的枚举和泛型特性
- **IDE友好** - 提供完整的PHPStorm元数据支持
- **协变支持** - `MetaList<@template-covariant T>`支持类型安全的协变
- **框架无关** - 可在任何PHP 8.1+项目中使用

## 安装

```bash
composer require kode/attributes
```

## 使用方法

### 基本用法

```php
use Kode\Attributes\Attr;

// 获取类的属性
$attributes = Attr::of(YourClass::class);

// 检查是否存在特定属性
if ($attributes->has(YourAttribute::class)) {
    // 获取特定属性
    $attribute = $attributes->get(YourAttribute::class);
}

// 获取方法的属性
$methodAttributes = Attr::of(YourClass::class)->getMethodAttrs('methodName');

// 获取属性的属性
$propertyAttributes = Attr::of(YourClass::class)->getPropertyAttrs('propertyName');
```

### 实际示例

```php
<?php

// 定义一个自定义属性
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public array $methods = ['GET'],
        public string $name = ''
    ) {}
}

// 在控制器中使用属性
#[Route('/users', methods: ['GET'], name: 'users.index')]
#[Route('/users', methods: ['POST'], name: 'users.create')]
class UserController
{
    #[Route('/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show($id)
    {
        // ...
    }
}

// 读取属性
$routes = Attr::of(UserController::class)->filter(fn($meta) => $meta->name === Route::class);

foreach ($routes as $route) {
    $instance = $route->newInstance();
    echo "Path: {$instance->path}, Methods: " . implode(', ', $instance->methods) . "\n";
}
```

### 高级功能

#### 缓存

Reader类内置缓存机制，默认使用内存缓存(ArrayCache)，你也可以注入自定义缓存实现：

```php
use Kode\Attributes\Reader;
use Kode\Attributes\ArrayCache;

$cache = new ArrayCache();
$reader = new Reader($cache);

// 获取缓存统计信息
$stats = $cache->getStats();
echo "缓存命中率: " . ($stats['hitRate'] * 100) . "%\n";
```

#### 扫描器

Scanner类可用于静态扫描目录中的类：

```php
use Kode\Attributes\Attr;

$scanner = Attr::scan(__DIR__ . '/src');

// 查找所有带有特定属性的类
foreach ($scanner->find(Route::class) as $class => $metas) {
    echo "发现带有Route属性的类: $class\n";
}
```

## API参考

详细的API文档请查看 [docs/api.md](docs/api.md) 文件。

## 贡献

欢迎提交Issue和Pull Request来改进这个项目。

## 许可证

MIT许可证，请查看 [LICENSE](LICENSE) 文件了解详细信息。