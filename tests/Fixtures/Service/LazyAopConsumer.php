<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Tests\Fixtures\Service;

use Sotvokun\Webman\Aop\Attribute\Lazy;

class LazyAopConsumer
{
    public function __construct(#[Lazy] public LazyAopService $service)
    {
    }
}
