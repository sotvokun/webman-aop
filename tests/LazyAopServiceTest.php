<?php

declare(strict_types=1);

namespace Sotvokun\Webman\Aop\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sotvokun\Webman\Aop\Container;
use Sotvokun\Webman\Aop\Tests\Fixtures\Interceptor\CountingInterceptor;
use Sotvokun\Webman\Aop\Tests\Fixtures\Service\LazyAopConsumer;
use Sotvokun\Webman\Aop\Tests\Fixtures\Service\LazyAopService;
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
}
