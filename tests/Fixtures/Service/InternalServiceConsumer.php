<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Tests\Fixtures\Service;

use DateTime;
use Sotvokun\Webman\Aop\Attribute\Lazy;

class InternalServiceConsumer
{
    public function __construct(#[Lazy] public DateTime $service)
    {
    }
}
