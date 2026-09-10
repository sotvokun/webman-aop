<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Attribute;

use Attribute;
use Sotvokun\Webman\Aop\MethodInterceptor;

#[Attribute(Attribute::TARGET_METHOD)]
abstract class Aspect
{
    /**
     * @return list<MethodInterceptor|class-string<MethodInterceptor>>
     */
    abstract public static function interceptors(): array;
}
