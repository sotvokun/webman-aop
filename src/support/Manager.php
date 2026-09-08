<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\support;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Ray\Aop\Aspect;
use Ray\Aop\Matcher;
use Ray\Aop\MethodInterceptor;
use RuntimeException;
use Sotvokun\Webman\Aop\Aspect as AspectAttribute;

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
        if (!is_dir($this->generatedClassDirectory) && !mkdir($this->generatedClassDirectory, 0775, true) && !is_dir($this->generatedClassDirectory)) {
            throw new RuntimeException("Unable to create AOP cache directory: {$this->generatedClassDirectory}");
        }

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
        $aspect = new Aspect($this->generatedClassDirectory);
        $matcher = new Matcher();

        foreach ($this->scanner->attributesFor($class) as $attribute) {
            $aspect->bind(
                $matcher->any(),
                $matcher->annotatedWith($attribute),
                $this->resolveInterceptors($attribute, $container),
            );
        }

        return $aspect->newInstance($class, $arguments);
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
