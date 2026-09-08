<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop;

/**
 * Project-level alias for the invocation passed to method interceptors.
 *
 * An alias is required so implementations can type-hint this namespace while
 * remaining compatible with the invocation instance created by Ray.Aop.
 *
 * @see \Ray\Aop\MethodInvocation
 */
class_alias(\Ray\Aop\MethodInvocation::class, __NAMESPACE__ . '\\MethodInvocation');
