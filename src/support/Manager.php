<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\support;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Ray\Aop\Bind;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\Weaver;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Sotvokun\Webman\Aop\Attribute\Aspect as AspectAttribute;

use function is_dir;
use function is_string;
use function mkdir;

/** Configures Ray.Aop for every target discovered by AopScanner. */
final class Manager
{
    private readonly Scanner $scanner;

    /** @param list<string> $scanDirectories */
    public function __construct(private readonly string $generatedClassDirectory, array $scanDirectories)
    {
        $this->scanner = new Scanner($scanDirectories);
    }

    /** @param class-string $class */
    public function shouldWeave(string $class): bool
    {
        return $this->scanner->shouldWeave($class);
    }

    /**
     * @param class-string $class
     * @param list<mixed>  $arguments
     */
    public function newInstance(string $class, array $arguments, Container $container): object
    {
        return $this->weaver($class, $container)->newInstance($class, $arguments);
    }

    /**
     * Create a lazy proxy for the generated AOP class.
     *
     * PHP lazy proxies can only attach an instance of their own class (or a
     * compatible parent). Ray.Aop returns a generated child class, so that
     * generated class—not the original service class—must be made lazy.
     *
     * @param class-string $class
     * @param callable(): list<mixed> $argumentsFactory
     * @param callable(object): void $afterResolving
     */
    public function newLazyProxy(
        string $class,
        Container $container,
        callable $argumentsFactory,
        callable $afterResolving,
    ): object
    {
        $weaver = $this->weaver($class, $container);
        $aopClass = $weaver->weave($class);
        $reflector = new ReflectionClass($aopClass);

        return $reflector->newLazyProxy(function (object $proxy) use ($weaver, $class, $argumentsFactory, $afterResolving): object {
            $instance = $weaver->newInstance($class, $argumentsFactory());
            $afterResolving($instance);

            return $instance;
        });
    }

    /** @param class-string $class */
    private function weaver(string $class, Container $container): Weaver
    {
        $this->ensureGeneratedClassDirectory();
        $bind = new Bind();
        $reflection = new ReflectionClass($class);

        foreach ($this->scanner->attributesFor($class) as $attribute) {
            $interceptors = $this->resolveInterceptors($attribute, $container);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF) !== []) {
                    $bind->bindInterceptors($method->getName(), $interceptors);
                }
            }
        }

        return new Weaver($bind, $this->generatedClassDirectory);
    }

    private function ensureGeneratedClassDirectory(): void
    {
        if (!is_dir($this->generatedClassDirectory) && !mkdir($this->generatedClassDirectory, 0775, true) && !is_dir($this->generatedClassDirectory)) {
            throw new RuntimeException("Unable to create AOP cache directory: {$this->generatedClassDirectory}");
        }
    }

    /**
     * @param class-string<AspectAttribute> $attribute
     *
     * @return list<MethodInterceptor>
     */
    private function resolveInterceptors(string $attribute, Container $container): array
    {
        $interceptors = [];
        foreach ($attribute::interceptors() as $interceptor) {
            $instance = is_string($interceptor) ? $container->make($interceptor) : $interceptor;
            if (!$instance instanceof MethodInterceptor) {
                throw new InvalidArgumentException("{$attribute} must return Ray\\Aop\\MethodInterceptor instances or class names.");
            }
            $interceptors[] = $instance;
        }

        return $interceptors;
    }
}
