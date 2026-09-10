<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Tests\Fixtures\Interceptor;

use Sotvokun\Webman\Aop\MethodInterceptor;
use Sotvokun\Webman\Aop\MethodInvocation;

final class CountingInterceptor implements MethodInterceptor
{
    public static int $calls = 0;

    public function invoke(MethodInvocation $invocation): mixed
    {
        self::$calls++;

        return $invocation->proceed();
    }
}
