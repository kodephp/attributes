# Kode Attributes

Lightweight, robust attribute reader for PHP 8.1+, designed as a foundation for the kodephp framework and compatible with any PHP framework.

## Features

- **Zero Dependencies**: Only uses PHP native functionality
- **High Performance**: Built-in caching and lazy instantiation
- **Framework Agnostic**: Works with Laravel, Symfony, ThinkPHP8, Webman, and more
- **IDE Friendly**: Full type hinting and PHPDoc annotations
- **Covariant Support**: Safe generic typing with `@template-covariant`
- **Secure**: Encapsulates Reflection API to prevent direct exposure
- **Extensible**: Pluggable cache system and scanner capabilities

## Installation

```bash
composer require kode/attributes
```

## Usage

### Basic Usage

```php
use Kode\Attributes\Attr;

// Check if a class has an attribute
if (Attr::has(MyClass::class, MyAttribute::class)) {
    // Get the attribute
    $meta = Attr::get(MyClass::class, MyAttribute::class);
    $instance = $meta->getInstance();
}

// Get all attributes of a class
$attributes = Attr::of(MyClass::class);
foreach ($attributes as $meta) {
    echo $meta->name;
}
```

### Advanced Usage

```php
use Kode\Attributes\Reader;
use Kode\Attributes\ArrayCache;

// Create a reader with custom cache
$reader = new Reader(new ArrayCache());

// Get class attributes
$classList = $reader->getClassAttrs(MyClass::class);

// Get method attributes
$methodList = $reader->getMethodAttrs(MyClass::class, 'myMethod');

// Get property attributes
$propertyList = $reader->getPropertyAttrs(MyClass::class, 'myProperty');
```

### Filtering and Mapping

```php
use Kode\Attributes\Attr;

// Filter attributes
$filtered = Attr::of(MyClass::class)
    ->filter(fn($meta) => is_subclass_of($meta->name, BaseAttribute::class));

// Map attributes to instances
$instances = Attr::of(MyClass::class)
    ->map(fn($meta) => $meta->getInstance());
```

### Practical Example

```php
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET'
    ) {
    }
}

#[Route('/api/users', 'GET')]
#[Route('/api/users', 'POST')]
class UserController
{
    #[Route('/api/users/{id}', 'GET')]
    public function getUser(int $id): void
    {
        // Method implementation
    }
}

// Get all routes for the controller
$routes = Attr::of(UserController::class);

// Filter to only POST routes
$postRoutes = $routes->filter(fn($meta) => $meta->getInstance()->method === 'POST');

// Map to path strings
$paths = $routes->map(fn($meta) => $meta->getInstance()->path);
```

## API Overview

### Main Classes

- `Attr`: Static facade for simple access
- `Reader`: Main attribute reader implementation
- `Meta`: Attribute metadata wrapper
- `MetaList`: Collection of attribute metadata
- `ArrayCache`: In-memory cache implementation
- `Scanner`: Static directory scanner (optional)

### Key Interfaces

- `ReaderInterface`: Contract for attribute readers
- `CacheInterface`: Contract for cache implementations

## Documentation

For detailed API documentation, see [docs/api.md](docs/api.md).

For usage examples, see [docs/example.php](docs/example.php).

### 中文文档

有关中文使用说明，请查看 [README_ZH.md](README_ZH.md)。

## Requirements

- PHP 8.1 or higher

## License
Apache License 2.0