<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop;

use Closure;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\SelfBuilding;
use Sotvokun\Webman\Aop\Attribute\Lazy;
use Sotvokun\Webman\Aop\support\AopLazyProxyFactory;
use Sotvokun\Webman\Aop\support\Config;
use Sotvokun\Webman\Aop\support\LazyProxyFactory;
use Sotvokun\Webman\Aop\support\Manager;

/** Webman's container with transparent Ray.Aop construction and lazy injection. */
final class Container extends IlluminateContainer
{
    private Manager|null $aop = null;
    private LazyProxyFactory|null $lazy = null;
    private AopLazyProxyFactory|null $aopLazy = null;

    public function build($concrete)
    {
        if ($concrete instanceof Closure || !is_string($concrete) || is_a($concrete, SelfBuilding::class, true)) {
            return parent::build($concrete);
        }

        $aop = $this->aop();
        if (!$aop->shouldWeave($concrete)) {
            return parent::build($concrete);
        }

        $reflector = new ReflectionClass($concrete);
        $arguments = $this->resolveAopArguments($concrete);

        $instance = $aop->newInstance($concrete, $arguments, $this);
        $this->fireAfterResolvingAttributeCallbacks($reflector->getAttributes(), $instance);

        return $instance;
    }

    protected function resolveClass(ReflectionParameter $parameter, ?string $className = null): mixed
    {
        if ($parameter->getAttributes(Lazy::class, ReflectionAttribute::IS_INSTANCEOF) === []) {
            return parent::resolveClass($parameter, $className);
        }

        $className ??= $this->classNameFor($parameter);
        if ($className === null) {
            throw new LogicException("Lazy dependency \${$parameter->getName()} must have a concrete class type.");
        }

        $reflector = new ReflectionClass($className);
        $aop = $this->aop();
        if ($aop->shouldWeave($className)) {
            return $this->aopLazy()->create(
                $className,
                $reflector,
                $parameter,
                $this,
                fn (): array => $this->resolveAopArguments($className),
                function (object $instance) use ($reflector): void {
                    $this->fireAfterResolvingAttributeCallbacks($reflector->getAttributes(), $instance);
                },
            );
        }

        return $this->lazy()->create($reflector, $parameter, fn (object $proxy): object => $this->make($className));
    }

    private function classNameFor(ReflectionParameter $parameter): string|null
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();
        $class = $parameter->getDeclaringClass();

        return match ($name) {
            'self' => $class?->getName(),
            'parent' => $class?->getParentClass()?->getName(),
            default => $name,
        };
    }

    /** @param class-string $concrete
     *  @return list<mixed>
     */
    private function resolveAopArguments(string $concrete): array
    {
        $constructor = (new ReflectionClass($concrete))->getConstructor();
        $this->buildStack[] = $concrete;

        try {
            return $constructor === null ? [] : $this->resolveDependencies($constructor->getParameters());
        } finally {
            array_pop($this->buildStack);
        }
    }

    private function aop(): Manager
    {
        return $this->aop ??= new Manager(
            Config::getClassPath() . DIRECTORY_SEPARATOR . getmypid(),
            Config::getScanDirs(),
        );
    }

    private function lazy(): LazyProxyFactory
    {
        return $this->lazy ??= new LazyProxyFactory();
    }

    private function aopLazy(): AopLazyProxyFactory
    {
        return $this->aopLazy ??= new AopLazyProxyFactory($this->aop(), $this->lazy());
    }
}
