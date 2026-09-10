<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Tests;

use PHPUnit\Framework\TestCase;
use LogicException;
use ReflectionClass;
use Sotvokun\Webman\Aop\Container;
use Sotvokun\Webman\Aop\Tests\Fixtures\Interceptor\CountingInterceptor;
use Sotvokun\Webman\Aop\Tests\Fixtures\Service\InternalServiceConsumer;
use Sotvokun\Webman\Aop\Tests\Fixtures\Service\LazyAopConsumer;
use Sotvokun\Webman\Aop\Tests\Fixtures\Service\LazyAopService;
use Sotvokun\Webman\Aop\Tests\Fixtures\Service\StaticPropertyConsumer;
use Webman\Config;

final class LazyAopServiceTest extends TestCase
{
    protected function setUp(): void
    {
        Config::clear();
        Config::load(__DIR__ . '/config');
        CountingInterceptor::$calls = 0;
        LazyAopService::$constructions = 0;
    }

    public function testLazyDependencyCanUseAop(): void
    {
        $consumer = (new Container())->make(LazyAopConsumer::class);

        self::assertSame(0, LazyAopService::$constructions);
        self::assertSame(0, CountingInterceptor::$calls);
        self::assertTrue((new ReflectionClass($consumer->service))->isUninitializedLazyObject($consumer->service));

        self::assertSame('ready', $consumer->service->value());
        self::assertSame(1, LazyAopService::$constructions);
        self::assertSame(1, CountingInterceptor::$calls);
    }

    public function testRejectsDependencyWithoutBackedInstanceProperty(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Lazy dependency $service must have at least one non-static, non-virtual instance property.');

        (new Container())->make(StaticPropertyConsumer::class);
    }

    public function testRejectsInternalDependency(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Lazy dependency $service cannot be lazily proxied because DateTime is internal or extends an internal class.');

        (new Container())->make(InternalServiceConsumer::class);
    }
}
