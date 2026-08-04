<?php

declare(strict_types=1);

namespace Kode\Attributes\Exception;

use RuntimeException;
use Throwable;

/**
 * 属性实例化失败异常。
 *
 * 常见原因：属性类不存在、构造参数与声明不匹配、目标类型不被 #[Attribute] 允许、
 * 或不可重复属性被重复声明（PHP 在 newInstance() 时才做校验）。
 *
 * 继承自 RuntimeException，保持与 1.x 的向后兼容。
 *
 * @package Kode\Attributes\Exception
 * @author KodePHP <382601296@qq.com>
 */
final class AttributeInstantiationException extends RuntimeException implements AttributeException
{
    /**
     * 创建实例化失败异常。
     *
     * @param string $attribute 属性类名
     * @param Throwable $previous 原始异常
     * @param string|null $subject 属性所在目标的描述
     */
    public static function forAttribute(string $attribute, Throwable $previous, ?string $subject = null): self
    {
        $where = $subject !== null ? sprintf('（位于 %s）', $subject) : '';

        return new self(
            sprintf('无法实例化属性 "%s"%s：%s', $attribute, $where, $previous->getMessage()),
            0,
            $previous
        );
    }

    /**
     * 创建读取失败异常。
     *
     * @param string $subject 目标描述
     * @param Throwable $previous 原始异常
     */
    public static function forSubject(string $subject, Throwable $previous): self
    {
        return new self(
            sprintf('读取 %s 上的属性失败：%s', $subject, $previous->getMessage()),
            0,
            $previous
        );
    }
}
