<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * 属性缓存接口。
 * 
 * 定义属性元数据缓存的契约，用于提升反射性能。
 * 支持自定义缓存驱动（如APCu、Redis、文件缓存等）。
 * 
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
interface CacheInterface
{
    /**
     * 获取缓存值，不存在时通过加载器获取并缓存。
     * 
     * @param string $key 缓存键
     * @param callable(): mixed $loader 值加载器回调函数
     * @return mixed 缓存值
     */
    public function get(string $key, callable $loader): mixed;

    /**
     * 检查缓存键是否存在。
     * 
     * @param string $key 缓存键
     * @return bool 是否存在
     */
    public function has(string $key): bool;

    /**
     * 设置缓存值。
     * 
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     */
    public function set(string $key, mixed $value): void;

    /**
     * 删除指定缓存键。
     * 
     * @param string $key 缓存键
     */
    public function delete(string $key): void;

    /**
     * 清除所有缓存值。
     */
    public function clear(): void;
}
