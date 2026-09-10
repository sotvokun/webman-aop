<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\support;

use LogicException;
use ReflectionClass;
use ReflectionParameter;

/** Creates native PHP lazy proxies after validating their engine constraints. */
final class LazyProxyFactory
{
    /**
     * @param callable(object): object $factory
     */
    public function create(ReflectionClass $class, ReflectionParameter $parameter, callable $factory): object
    {
        $this->assertProxyable($class, $parameter);

        return $class->newLazyProxy($factory);
    }

    /** Validate a requested lazy dependency before creating a proxy. */
    public function assertProxyable(ReflectionClass $class, ReflectionParameter $parameter): void
    {
        $dependency = "Lazy dependency \${$parameter->getName()}";
        if (!$class->isInstantiable()) {
            throw new LogicException("{$dependency} must have an instantiable class type.");
        }

        if ($this->extendsUnsupportedInternalClass($class)) {
            throw new LogicException("{$dependency} cannot be lazily proxied because {$class->getName()} is internal or extends an internal class.");
        }

        if (!$this->hasBackedInstanceProperty($class)) {
            throw new LogicException("{$dependency} must have at least one non-static, non-virtual instance property.");
        }
    }

    private function extendsUnsupportedInternalClass(ReflectionClass $class): bool
    {
        for ($candidate = $class; $candidate !== false; $candidate = $candidate->getParentClass()) {
            if ($candidate->isInternal() && $candidate->getName() !== \stdClass::class) {
                return true;
            }
        }

        return false;
    }

    private function hasBackedInstanceProperty(ReflectionClass $class): bool
    {
        for ($candidate = $class; $candidate !== false; $candidate = $candidate->getParentClass()) {
            foreach ($candidate->getProperties() as $property) {
                if (!$property->isStatic() && !$property->isVirtual()) {
                    return true;
                }
            }
        }

        return false;
    }
}
