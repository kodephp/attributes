<?php

declare(strict_types=1);

namespace Kode\Attributes;

use ReflectionParameter;

/**
 * 属性读取器接口。
 * 
 * 定义属性读取器的契约，支持从各种目标读取属性。
 * 
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
interface ReaderInterface
{
    /**
     * 获取类的属性。
     * 
     * @param string $class 类名
     * @return MetaList 属性元数据集合
     */
    public function getClassAttrs(string $class): MetaList;

    /**
     * 获取方法的属性。
     * 
     * @param string $class 类名
     * @param string $method 方法名
     * @return MetaList 属性元数据集合
     */
    public function getMethodAttrs(string $class, string $method): MetaList;

    /**
     * 获取属性的属性。
     * 
     * @param string $class 类名
     * @param string $property 属性名
     * @return MetaList 属性元数据集合
     */
    public function getPropertyAttrs(string $class, string $property): MetaList;

    /**
     * 获取函数的属性。
     * 
     * @param string $function 函数名
     * @return MetaList 属性元数据集合
     */
    public function getFunctionAttrs(string $function): MetaList;

    /**
     * 获取参数的属性。
     * 
     * @param ReflectionParameter $param 参数反射实例
     * @return MetaList 属性元数据集合
     */
    public function getParameterAttrs(ReflectionParameter $param): MetaList;
}
