<?php

/*
 * This file is part of the kode/attributes package.
 *
 * (c) kode (KodePHP) <382601296@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
 * @author  kode (KodePHP) <382601296@qq.com>
 * @license Apache-2.0
 * @link    https://github.com/kodephp/attributes
 */
interface AttributeException extends Throwable
{
}
