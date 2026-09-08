<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop;

use Closure;
use ReflectionClass;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Container\SelfBuilding;
use Sotvokun\Webman\Aop\support\Config;
use Sotvokun\Webman\Aop\support\Manager;

/** Webman's container with transparent Ray.Aop construction for scanned classes. */
final class Container extends IlluminateContainer
{
    private Manager|null $aop = null;

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
        $constructor = $reflector->getConstructor();
        $this->buildStack[] = $concrete;

        try {
            $arguments = $constructor === null ? [] : $this->resolveDependencies($constructor->getParameters());
        } finally {
            array_pop($this->buildStack);
        }

        $instance = $aop->newInstance($concrete, $arguments, $this);
        $this->fireAfterResolvingAttributeCallbacks($reflector->getAttributes(), $instance);

        return $instance;
    }

    private function aop(): Manager
    {
        return $this->aop ??= new Manager(
            Config::getClassPath() . DIRECTORY_SEPARATOR . getmypid(),
            Config::getScanDirs(),
        );
    }
}
