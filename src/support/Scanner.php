<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\support;

use Composer\ClassMapGenerator\ClassMapGenerator;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Sotvokun\Webman\Aop\Aspect;

use function array_unique;
use function class_exists;
use function is_dir;

/** Finds classes with public methods marked by an AopAttribute subclass. */
final class Scanner
{
    /** @var array<class-string, list<class-string<Aspect>>> */
    private array $targets = [];

    /** @var array<class-string, string> */
    private array $classMap;

    /** @var array<class-string, true> */
    private array $inspected = [];

    /** @param list<string> $directories */
    public function __construct(array $directories)
    {
        $classMapGenerator = (new ClassMapGenerator())->avoidDuplicateScans();
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                $classMapGenerator->scanPaths($directory);
            }
        }

        $this->classMap = $classMapGenerator->getClassMap()->getMap();
    }

    /** @param class-string $class */
    public function shouldWeave(string $class): bool
    {
        $this->inspectIfScanned($class);

        return isset($this->targets[$class]);
    }

    /**
     * @param class-string $class
     *
     * @return list<class-string<Aspect>>
     */
    public function attributesFor(string $class): array
    {
        $this->inspectIfScanned($class);

        return $this->targets[$class] ?? [];
    }

    /** @param class-string $class */
    private function inspectIfScanned(string $class): void
    {
        if (isset($this->inspected[$class])) {
            return;
        }
        $this->inspected[$class] = true;

        if (!isset($this->classMap[$class])) {
            return;
        }

        if (!class_exists($class, false)) {
            require_once $this->classMap[$class];
        }
        if (class_exists($class, false)) {
            $this->inspect($class);
        }
    }

    /** @param class-string $class */
    private function inspect(string $class): void
    {
        $reflection = new ReflectionClass($class);
        $this->assertWeavableClass($reflection);
        $attributes = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $methodAttributes = $method->getAttributes(Aspect::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($methodAttributes === []) {
                continue;
            }

            $this->assertWeavableMethod($reflection, $method);
            foreach ($methodAttributes as $attribute) {
                /** @var class-string<Aspect> $attributeClass */
                $attributeClass = $attribute->getName();
                $attributes[] = $attributeClass;
            }
        }

        if ($attributes === []) {
            return;
        }
        $this->targets[$class] = array_values(array_unique($attributes));
    }

    private function assertWeavableClass(ReflectionClass $class): void
    {
        if ($class->isFinal() || !$class->isInstantiable()) {
            throw new InvalidArgumentException("AOP target {$class->getName()} must be a non-final, instantiable class.");
        }
    }

    private function assertWeavableMethod(ReflectionClass $class, ReflectionMethod $method): void
    {
        if (!$method->isPublic() || $method->isStatic() || $method->isFinal()) {
            throw new InvalidArgumentException(sprintf(
                'AOP target %s::%s() must be public, non-static, and non-final.',
                $class->getName(),
                $method->getName(),
            ));
        }
    }
}
