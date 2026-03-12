<?php

declare(strict_types=1);

namespace Kode\Attributes;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionAttribute;

/**
 * 属性读取器实现类。
 * 
 * 使用PHP反射API读取各类目标（类、方法、属性、函数、参数）的属性，
 * 并提供一致的访问接口。内置缓存机制，支持自定义缓存驱动。
 * 
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class Reader implements ReaderInterface
{
    /**
     * 缓存实现实例。
     */
    private CacheInterface $cache;

    /**
     * 创建新的Reader实例。
     * 
     * @param CacheInterface|null $cache 可选的缓存实现，默认使用ArrayCache
     */
    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? new ArrayCache();
    }

    /**
     * 获取目标的属性（自动检测目标类型）。
     * 
     * @param object|string $target 目标类名或对象实例
     * @return MetaList 属性元数据集合
     * @throws \InvalidArgumentException 当目标类型无效时抛出
     */
    public function getAttributes(object|string $target): MetaList
    {
        if (is_string($target)) {
            return $this->getClassAttrs($target);
        }

        if (is_object($target)) {
            return $this->getObjectAttrs($target);
        }

        throw new \InvalidArgumentException('目标必须是类名或对象实例');
    }

    /**
     * 获取对象的属性。
     * 
     * @param object $object 目标对象实例
     * @return MetaList 属性元数据集合
     */
    public function getObjectAttrs(object $object): MetaList
    {
        $class = $object::class;
        return $this->getClassAttrs($class);
    }

    /**
     * {@inheritDoc}
     */
    public function getClassAttrs(string $class): MetaList
    {
        $key = "class:{$class}";
        
        return $this->cache->get($key, function() use ($class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes, $reflection);
        });
    }

    /**
     * 获取类的所有方法属性。
     * 
     * @param string $class 类名
     * @return array<string, MetaList> 方法名 => 属性集合
     */
    public function getAllMethodAttrs(string $class): array
    {
        $key = "all_methods:{$class}";
        
        return $this->cache->get($key, function() use ($class) {
            $reflection = new ReflectionClass($class);
            $result = [];
            
            foreach ($reflection->getMethods() as $method) {
                $attrs = $this->createMetaList($method->getAttributes(), $method);
                if (count($attrs) > 0) {
                    $result[$method->getName()] = $attrs;
                }
            }
            
            return $result;
        });
    }

    /**
     * 获取类的所有属性属性。
     * 
     * @param string $class 类名
     * @return array<string, MetaList> 属性名 => 属性集合
     */
    public function getAllPropertyAttrs(string $class): array
    {
        $key = "all_properties:{$class}";
        
        return $this->cache->get($key, function() use ($class) {
            $reflection = new ReflectionClass($class);
            $result = [];
            
            foreach ($reflection->getProperties() as $property) {
                $attrs = $this->createMetaList($property->getAttributes(), $property);
                if (count($attrs) > 0) {
                    $result[$property->getName()] = $attrs;
                }
            }
            
            return $result;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getMethodAttrs(string $class, string $method): MetaList
    {
        $key = "method:{$class}:{$method}";
        
        return $this->cache->get($key, function() use ($class, $method) {
            $reflection = new ReflectionMethod($class, $method);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes, $reflection);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyAttrs(string $class, string $property): MetaList
    {
        $key = "property:{$class}:{$property}";
        
        return $this->cache->get($key, function() use ($class, $property) {
            $reflection = new ReflectionProperty($class, $property);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes, $reflection);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctionAttrs(string $function): MetaList
    {
        $key = "function:{$function}";
        
        return $this->cache->get($key, function() use ($function) {
            $reflection = new ReflectionFunction($function);
            $attributes = $reflection->getAttributes();
            return $this->createMetaList($attributes, $reflection);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getParameterAttrs(ReflectionParameter $param): MetaList
    {
        $declaringFunc = $param->getDeclaringFunction();
        $funcName = $declaringFunc ? $declaringFunc->getName() : 'unknown';
        $key = "parameter:{$funcName}:" . $param->getName();
        
        return $this->cache->get($key, function() use ($param) {
            $attributes = $param->getAttributes();
            return $this->createMetaList($attributes, $param);
        });
    }

    /**
     * 获取类常量的属性。
     * 
     * @param string $class 类名
     * @param string $constant 常量名
     * @return MetaList 属性元数据集合
     */
    public function getConstantAttrs(string $class, string $constant): MetaList
    {
        $key = "constant:{$class}:{$constant}";
        
        return $this->cache->get($key, function() use ($class, $constant) {
            $reflection = new ReflectionClass($class);
            $constReflection = $reflection->getReflectionConstant($constant);
            
            if ($constReflection === false) {
                return new MetaList([]);
            }
            
            $attributes = $constReflection->getAttributes();
            return $this->createMetaList($attributes, $constReflection);
        });
    }

    /**
     * 从反射属性数组创建MetaList实例。
     * 
     * @param array<ReflectionAttribute> $attributes 反射属性数组
     * @param \Reflector|null $reflector 所属反射对象
     * @return MetaList 属性元数据集合
     */
    private function createMetaList(array $attributes, ?\Reflector $reflector = null): MetaList
    {
        $metaList = [];
        
        foreach ($attributes as $attribute) {
            try {
                $metaList[] = new Meta($attribute, $reflector);
            } catch (\Throwable $e) {
                continue;
            }
        }
        
        return new MetaList($metaList);
    }

    /**
     * 清除所有缓存。
     */
    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * 获取缓存实例。
     * 
     * @return CacheInterface 缓存实例
     */
    public function getCache(): CacheInterface
    {
        return $this->cache;
    }
}
