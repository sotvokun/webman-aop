# Webman AOP

`sotvokun/webman-aop` 将 [Ray.Aop](https://github.com/ray-di/Ray.Aop) 集成到 Webman，并提供基于 Illuminate 的容器。为 Service 的公开方法添加切面 Attribute 后，只要该 Service 由容器解析，拦截器便会自动生效。

## 环境要求

- PHP 8.3+
- Webman 2.1+

## 安装

```bash
composer require sotvokun/webman-aop
```

安装时 Webman 会自动将插件配置导出到 `config/plugin/sotvokun/webman-aop/`。

在 `config/plugin/sotvokun/webman-aop/app.php` 配置代理类目录和扫描目录：

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

在 `config/container.php` 中创建或替换为插件提供的容器：

```php
<?php

use Sotvokun\Webman\Aop\Container;

return new Container();
```

## 定义切面

切面是一个继承 `Sotvokun\Webman\Aop\Aspect` 的 PHP Attribute。其 `interceptors()` 方法返回拦截器实例或拦截器类名。

```php
<?php

namespace module\order\aspect;

use Attribute;
use module\order\interceptor\ExampleInterceptor;
use Sotvokun\Webman\Aop\Aspect;

#[Attribute(Attribute::TARGET_METHOD)]
final class ExampleAspect extends Aspect
{
    public static function interceptors(): array
    {
        return [ExampleInterceptor::class];
    }
}
```

拦截器需要实现 `Sotvokun\Webman\Aop\MethodInterceptor`：

```php
<?php

namespace module\order\interceptor;

use Sotvokun\Webman\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;

final class ExampleInterceptor implements MethodInterceptor
{
    public function invoke(MethodInvocation $invocation): mixed
    {
        // 调用目标方法前的逻辑。
        $result = $invocation->proceed();
        // 调用目标方法后的逻辑。

        return $result;
    }
}
```

在扫描目录中的 Service 方法上使用切面：

```php
<?php

namespace module\order\service;

use module\order\aspect\ExampleAspect;

class OrderService
{
    #[ExampleAspect]
    public function create(array $input): void
    {
        // 业务逻辑。
    }
}
```

正常通过 Webman 容器或构造函数注入使用 `OrderService`，不需要调用额外的工厂方法：

```php
final class OrderController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }
}
```

## 拦截器依赖注入

当 `interceptors()` 返回拦截器类名时，插件会通过 Webman 容器创建该拦截器，因此构造函数依赖会被正常注入：

```php
final class ExampleInterceptor implements MethodInterceptor
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

若返回 `new ExampleInterceptor()`，该拦截器由业务代码自行构造，不会经过容器注入。

## 延迟注入

为构造函数中带类类型的依赖添加 `#[Sotvokun\Webman\Aop\Attribute\Lazy]`。容器会先注入代理，直到首次访问该服务的对象状态时才解析真实服务：

```php
use Sotvokun\Webman\Aop\Attribute\Lazy;

final class ReportController
{
    public function __construct(#[Lazy] private ReportService $reports)
    {
    }
}
```

依赖类型必须是包含至少一个非静态属性的可实例化类。接口、联合类型及没有实例属性的类无法进行延迟代理。

使用该依赖的服务可以使用 AOP Attribute。构造 `ReportController` 时会注入代理，`ReportService` 仍保持未实例化状态：

```php
use module\order\aspect\ExampleAspect;

final class ReportController
{
    public function __construct(#[Lazy] private ReportService $reports)
    {
    }

    #[ExampleAspect]
    public function show(): array
    {
        return $this->reports->latest();
    }
}
```

### 延迟 AOP 服务

`#[Lazy]` 可以用于其公开方法带有 AOP Attribute 的依赖。容器会先生成 Ray.Aop 的子类，再基于这个生成类创建 PHP lazy proxy。因此服务首次初始化时，真实对象与 lazy proxy 的类相同，拦截器也会正常生效：

```php
use module\order\aspect\ExampleAspect;

final class ReportService
{
    public function __construct(private ReportClient $client)
    {
    }

    #[ExampleAspect]
    public function latest(): array
    {
        // ...
    }
}
```

## 使用限制

- 仅扫描 `scan_dirs` 配置的目录。
- 目标类不能是 `final`，且必须可实例化。
- 被拦截的方法必须是 `public`、非 `static`、非 `final`。
- Service 必须通过容器获取；直接 `new OrderService()` 会绕过 AOP。
- Ray.Aop 会将生成的代理类写入 `class_path`。

## 代理缓存

每个 worker 会将代理类写入 `class_path/<worker-pid>`。worker reload 后，替代它的新 worker 拥有新的 PID，会生成全新的代理类，不会影响仍在运行的其他 worker。`Bootstrap` 会在每次 Webman 完整重启时清空整个 `class_path`；清理过程使用文件锁，因此多个 worker 不会并发清理同一个目录。
