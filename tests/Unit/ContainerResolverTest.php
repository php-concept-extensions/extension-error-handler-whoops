<?php declare(strict_types=1);

namespace Tests\Unit;

use Concept\Extensions\ErrorHandlerWhoops\ContainerResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;

final class ContainerResolverTest extends TestCase
{
    public function testTryGetReturnsService(): void
    {
        $service = new stdClass();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(stdClass::class)->willReturn($service);

        $this->assertSame($service, ContainerResolver::tryGet($container, stdClass::class));
    }

    public function testTryGetReturnsNullWhenContainerThrows(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException(new RuntimeException('missing'));

        $this->assertNull(ContainerResolver::tryGet($container, stdClass::class));
    }
}
