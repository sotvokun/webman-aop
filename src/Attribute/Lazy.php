<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Attribute;

use Attribute;

/** Marks a class-typed constructor dependency for lazy injection. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Lazy
{
}
