<?php

declare(strict_types=1);

namespace Kode\Attributes\Exception;

use Throwable;

/**
 * 本包所有异常的统一标记接口。
 *
 * 调用方可以只 catch 这一个接口，即可捕获 kode/attributes 抛出的全部异常，
 * 而无需关心具体是参数错误、目标缺失还是属性实例化失败。
 *
 * @package Kode\Attributes\Exception
 * @author KodePHP <382601296@qq.com>
 */
interface AttributeException extends Throwable
{
}
