# API Documentation

## Attr (Facade)

The main entry point for accessing attribute functionality.

### Methods

#### `reader(): Reader`
Get the attribute reader instance.

```php
$reader = Attr::reader();
```

#### `of(object|string $target): MetaList`
Get attributes for a target (class, method, property, etc.).

```php
$attributes = Attr::of(MyClass::class);
```

#### `has(object|string $target, string $attrClass): bool`
Check if a target has a specific attribute.

```php
if (Attr::has(MyClass::class, MyAttribute::class)) {
    // ...
}
```

#### `get(object|string $target, string $attrClass): ?Meta`
Get a specific attribute from a target.

```php
$meta = Attr::get(MyClass::class, MyAttribute::class);
```

## Reader

The main attribute reader implementation.

### Methods

#### `getClassAttrs(string $class): MetaList`
Get attributes for a class.

#### `getMethodAttrs(string $class, string $method): MetaList`
Get attributes for a method.

#### `getPropertyAttrs(string $class, string $property): MetaList`
Get attributes for a property.

#### `getFunctionAttrs(string $function): MetaList`
Get attributes for a function.

#### `getParameterAttrs(ReflectionParameter $param): MetaList`
Get attributes for a parameter.

## Meta

Wrapper for attribute metadata.

### Properties

#### `refAttr: ReflectionAttribute` (readonly)
The ReflectionAttribute instance.

#### `name: string` (readonly)
The attribute class name.

#### `args: array` (readonly)
The attribute arguments.

### Methods

#### `getInstance(): object`
Get the instantiated attribute object.

#### `isRepeatable(): bool`
Check if the attribute is repeatable.

#### `getTarget(): Target`
Get the target type of this attribute.

## MetaList

Collection of attribute metadata.

### Methods

#### `first(): ?Meta`
Get the first metadata object in the list.

#### `last(): ?Meta`
Get the last metadata object in the list.

#### `filter(callable $fn): self`
Filter the metadata objects using a callback function.

#### `map(callable $fn): array`
Apply a callback function to each metadata object.

#### `has(string $className): bool`
Check if the list contains an attribute of the specified class.

#### `get(string $className): ?Meta`
Get the first attribute of the specified class.

#### `all(): array`
Get all metadata objects as an array.

#### `merge(self $other): self`
Merge this list with another MetaList.

## CacheInterface

Interface for attribute cache implementations.

### Methods

#### `get(string $key, callable $loader): mixed`
Get a value from the cache, or store a new value if it doesn't exist.

#### `clear(): void`
Clear all cached values.

## ArrayCache

In-memory array cache implementation.

Implements the `CacheInterface`.

## Target

Attribute target types enum.

### Cases

- `Class`
- `Function`
- `Method`
- `Property`
- `Parameter`
- `All`

### Methods

#### `fromRef(Reflector $ref): self`
Create a Target from a Reflector instance.

## Flags

Attribute flags.

### Properties

- `inherit: bool` (readonly)
- `compileTime: bool` (readonly)
- `priority: int` (readonly)

## Scanner

Static attribute scanner.

### Methods

#### `exclude(string ...$patterns): self`
Exclude patterns from scanning.

#### `scan(string $dir): Generator`
Scan a directory for attributes.