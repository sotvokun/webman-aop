# Webman AOP

`sotvokun/webman-aop` integrates [Ray.Aop](https://github.com/ray-di/Ray.Aop) with Webman and provides an Illuminate-based container. Mark a public service method with an Aspect Attribute and the interceptor is applied automatically when the service is resolved from the container.

## Requirements

- PHP 8.3+
- Webman 2.1+

## Installation

```bash
composer require sotvokun/webman-aop
```

Webman automatically exports the plugin configuration to `config/plugin/sotvokun/webman-aop/` during installation.

Configure the generated proxy directory and directories to scan in `config/plugin/sotvokun/webman-aop/app.php`:

```php
<?php

return [
    'enable' => true,
    'class_path' => runtime_path('aop'),
    'scan_dirs' => [
        ...glob(base_path() . '/module/*/service'),
        ...glob(base_path() . '/module/*/query'),
    ],
];
```

Replace the default container in `config/container.php` with the package container:

```php
<?php

use Sotvokun\Webman\Aop\Container;

$container = new Container();

foreach (config('dependence.bind', []) as $abstract => $concrete) {
    $container->bind($abstract, $concrete);
}
foreach (config('dependence.singleton', []) as $abstract => $concrete) {
    $container->singleton($abstract, $concrete);
}

return $container;
```

## Define an Aspect

An Aspect is a PHP Attribute extending `Sotvokun\Webman\Aop\Aspect`. Its `interceptors()` method returns interceptor instances or class names.

```php
<?php

namespace module\order\aspect;

use Attribute;
use module\order\interceptor\TransactionInterceptor;
use Sotvokun\Webman\Aop\Aspect;

#[Attribute(Attribute::TARGET_METHOD)]
final class Transactional extends Aspect
{
    public static function interceptors(): array
    {
        return [TransactionInterceptor::class];
    }
}
```

An interceptor implements `Ray\Aop\MethodInterceptor`:

```php
<?php

namespace module\order\interceptor;

use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

final class TransactionInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        // Before invoking the target method.
        $result = $invocation->proceed();
        // After invoking the target method.

        return $result;
    }
}
```

Apply the Aspect to a service method located in a configured scan directory:

```php
<?php

namespace module\order\service;

use module\order\aspect\Transactional;

class OrderService
{
    #[Transactional]
    public function create(array $input): void
    {
        // Business logic.
    }
}
```

Resolve `OrderService` through Webman's container or constructor injection as usual. No factory call is needed:

```php
final class OrderController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }
}
```

## Dependency Injection in Interceptors

When `interceptors()` returns a class name, the package creates it through the Webman container. Constructor dependencies are therefore injected normally:

```php
final class TransactionInterceptor implements MethodInterceptor
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }
}
```

Returning `new TransactionInterceptor()` bypasses container construction for that interceptor.

## Constraints

- Only classes under `scan_dirs` are considered.
- Target classes must be non-final and instantiable.
- Intercepted methods must be `public`, non-static, and non-final.
- Services must be resolved by the container. Direct `new OrderService()` calls bypass AOP.
- Ray.Aop writes generated proxy classes to `class_path`.

## Generated Proxy Cache

`Bootstrap` clears `class_path` once per Webman restart, protected by a file lock so multiple workers do not clear it concurrently. The first resolution of a target service in each worker regenerates its proxy class.
