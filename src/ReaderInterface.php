<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Interface for attribute readers.
 * 
 * Defines the contract for reading attributes from various targets.
 * 
 * @package Kode\Attributes
 */
interface ReaderInterface
{
    /**
     * Get attributes for a class.
     * 
     * @param string $class The class name
     * @return MetaList
     */
    public function getClassAttrs(string $class): MetaList;

    /**
     * Get attributes for a method.
     * 
     * @param string $class The class name
     * @param string $method The method name
     * @return MetaList
     */
    public function getMethodAttrs(string $class, string $method): MetaList;

    /**
     * Get attributes for a property.
     * 
     * @param string $class The class name
     * @param string $property The property name
     * @return MetaList
     */
    public function getPropertyAttrs(string $class, string $property): MetaList;

    /**
     * Get attributes for a function.
     * 
     * @param string $function The function name
     * @return MetaList
     */
    public function getFunctionAttrs(string $function): MetaList;

    /**
     * Get attributes for a parameter.
     * 
     * @param \ReflectionParameter $param The parameter reflection
     * @return MetaList
     */
    public function getParameterAttrs(\ReflectionParameter $param): MetaList;
}