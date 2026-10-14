<?php declare(strict_types=1);

namespace Tests\Unit;

use Concept\Extensions\ErrorHandlerWhoops\Contracts\ExceptionReporterInterface;
use Concept\Extensions\ErrorHandlerWhoops\Contracts\HttpErrorRendererInterface;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\RenderHttpErrorHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\ReportExceptionHandler;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Whoops\Handler\Handler;
use Whoops\Run;

final class ReportExceptionHandlerTest extends TestCase
{
    public function testHandleReportsExceptionAndReturnsDone(): void
    {
        $reported = null;
        $reporter = $this->createMock(ExceptionReporterInterface::class);
        $reporter->expects($this->once())->method('report')->willReturnCallback(
            function(\Throwable $exception, string $uri) use (&$reported): void {
                $reported = [$exception->getMessage(), $uri];
            },
        );

        $handler = new ReportExceptionHandler(
            reporterFactory: static fn(): ExceptionReporterInterface => $reporter,
            uriFactory: static fn(): string => '/report',
        );
        $handler->setException(new RuntimeException('boom'));

        $this->assertSame(Handler::DONE, $handler->handle());
        $this->assertSame(['boom', '/report'], $reported);
    }

    public function testHandleSwallowsReporterFailures(): void
    {
        $reporter = $this->createMock(ExceptionReporterInterface::class);
        $reporter->method('report')->willThrowException(new RuntimeException('logger down'));

        $handler = new ReportExceptionHandler(static fn(): ExceptionReporterInterface => $reporter);
        $handler->setException(new RuntimeException('boom'));

        $this->assertSame(Handler::DONE, $handler->handle());
    }
}

final class RenderHttpErrorHandlerTest extends TestCase
{
    public function testHandleReturnsDoneWhenRequestFactoryReturnsNull(): void
    {
        $handler = new RenderHttpErrorHandler(
            rendererFactory: static fn(): HttpErrorRendererInterface => $this->createMock(HttpErrorRendererInterface::class),
            requestFactory: static fn(): null => null,
        );
        $handler->setException(new RuntimeException('boom'));

        $this->assertSame(Handler::DONE, $handler->handle());
    }

    public function testHandleUsesRendererStatusCodeAndReturnsQuit(): void
    {
        $renderer = $this->createMock(HttpErrorRendererInterface::class);
        $renderer->expects($this->once())->method('render')->with(
            $this->isInstanceOf(ServerRequestInterface::class),
            404,
            'not found',
        )->willReturn((new Response())->withStatus(404));

        $handler = new RenderHttpErrorHandler(
            rendererFactory: static fn(): HttpErrorRendererInterface => $renderer,
            requestFactory: static fn(): ServerRequestInterface => new ServerRequest(),
        );
        $exception = new class('not found', 404) extends RuntimeException {
            public function getStatusCode(): int
            {
                return 404;
            }
        };
        $handler->setException($exception);
        $handler->setRun(new Run());

        $this->assertSame(Handler::QUIT, $handler->handle());
    }
}
