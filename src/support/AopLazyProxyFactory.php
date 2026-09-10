<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\support;

use Illuminate\Container\Container;
use ReflectionClass;
use ReflectionParameter;

/** Composes Ray.Aop weaving with native PHP lazy proxy creation. */
final readonly class AopLazyProxyFactory
{
    public function __construct(
        private Manager $aop,
        private LazyProxyFactory $lazy,
    ) {
    }

    /**
     * @param class-string $class
     * @param callable(): list<mixed> $argumentsFactory
     * @param callable(object): void $afterResolving
     */
    public function create(
        string $class,
        ReflectionClass $serviceClass,
        ReflectionParameter $parameter,
        Container $container,
        callable $argumentsFactory,
        callable $afterResolving,
    ): object {
        $this->lazy->assertProxyable($serviceClass, $parameter);
        $weaver = $this->aop->createWeaver($class, $container);
        $aopClass = $weaver->weave($class);

        return $this->lazy->create(
            new ReflectionClass($aopClass),
            $parameter,
            function (object $proxy) use ($weaver, $class, $argumentsFactory, $afterResolving): object {
                $instance = $weaver->newInstance($class, $argumentsFactory());
                $afterResolving($instance);

                return $instance;
            },
        );
    }
}
