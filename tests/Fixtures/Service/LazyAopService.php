<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Tests\Fixtures\Service;

use Sotvokun\Webman\Aop\Tests\Fixtures\Aspect\CountingAspect;

class LazyAopService
{
    public static int $constructions = 0;

    public string $state;

    public function __construct()
    {
        self::$constructions++;
        $this->state = 'ready';
    }

    #[CountingAspect]
    public function value(): string
    {
        return $this->state;
    }
}
