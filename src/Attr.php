<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * 属性读取器全局门面类。
 * 
 * 提供统一的静态访问入口，简化属性操作。
 * 支持单例模式的Reader实例，避免重复创建。
 * 
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class Attr
{
    /**
     * Reader单例实例。
     */
    private static ?Reader $reader = null;

    /**
     * 私有构造函数，防止实例化。
     */
    private function __construct()
    {
    }

    /**
     * 获取Reader实例（单例模式）。
     * 
     * @return Reader 属性读取器实例
     */
    public static function reader(): Reader
    {
        if (self::$reader === null) {
            self::$reader = new Reader();
        }
        return self::$reader;
    }

    /**
     * 设置自定义Reader实例。
     * 
     * @param Reader $reader 自定义Reader实例
     */
    public static function setReader(Reader $reader): void
    {
        self::$reader = $reader;
    }

    /**
     * 创建目录扫描器实例。
     * 
     * @param string $dir 要扫描的目录路径
     * @return Scanner 扫描器实例
     */
    public static function scan(string $dir): Scanner
    {
        $scanner = new Scanner(self::reader());
        return $scanner;
    }

    /**
     * 获取目标的所有属性元数据。
     * 
     * @param object|string $target 目标类名或对象实例
     * @return MetaList 属性元数据集合
     */
    public static function of(object|string $target): MetaList
    {
        return self::reader()->getAttributes($target);
    }

    /**
     * 检查目标是否具有指定属性。
     * 
     * @param object|string $target 目标类名或对象实例
     * @param string $attrClass 要检查的属性类名
     * @return bool 是否存在该属性
     */
    public static function has(object|string $target, string $attrClass): bool
    {
        return self::of($target)->has($attrClass);
    }

    /**
     * 获取目标的指定属性元数据。
     * 
     * @param object|string $target 目标类名或对象实例
     * @param string $attrClass 要获取的属性类名
     * @return Meta|null 属性元数据，不存在则返回null
     */
    public static function get(object|string $target, string $attrClass): ?Meta
    {
        return self::of($target)->get($attrClass);
    }

    /**
     * 获取目标的所有指定类型属性元数据。
     * 
     * @param object|string $target 目标类名或对象实例
     * @param string $attrClass 要获取的属性类名
     * @return MetaList 属性元数据集合
     */
    public static function getAll(object|string $target, string $attrClass): MetaList
    {
        return self::of($target)->filter(fn(Meta $meta) => $meta->name === $attrClass);
    }

    /**
     * 清除缓存的Reader实例。
     * 用于测试或需要重置Reader时调用。
     */
    public static function clear(): void
    {
        self::$reader = null;
    }
}
