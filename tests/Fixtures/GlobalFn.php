<?php

declare(strict_types=1);

/*
 * 全局命名空间下的夹具：用于测试「函数目标」属性读取。
 * 该文件不带 namespace 声明，因此其中的类与函数都属于全局命名空间，
 * 不会与 Kode\Attributes\Tests 下的 PSR-4 映射冲突。
 */

#[Attribute(\Attribute::TARGET_FUNCTION)]
class KodeFnMarker
{
    public function __construct(public readonly string $label = 'fn') {}
}

#[KodeFnMarker('global-fn')]
function kode_attributes_global_fn(): void {}
