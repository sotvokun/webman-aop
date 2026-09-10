<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Tests\Fixtures\Aspect;

use Attribute;
use Sotvokun\Webman\Aop\Attribute\Aspect;
use Sotvokun\Webman\Aop\Tests\Fixtures\Interceptor\CountingInterceptor;

#[Attribute(Attribute::TARGET_METHOD)]
final class CountingAspect extends Aspect
{
    public static function interceptors(): array
    {
        return [CountingInterceptor::class];
    }
}
