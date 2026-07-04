<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Reporting;

use Concept\Extensions\ErrorHandlerWhoops\Contracts\ExceptionReporterInterface;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\ErrorLogHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\PhpErrorLogHandler;
use Psr\Container\ContainerInterface;
use Throwable;

final class WhoopsExceptionReporter implements ExceptionReporterInterface
{
    public function __construct(private readonly ContainerInterface $container) {}

    public function report(Throwable $exception): void
    {
        $phpErrorLogHandler = new PhpErrorLogHandler();
        $phpErrorLogHandler->setException($exception);
        $phpErrorLogHandler->handle();

        $errorLogHandler = new ErrorLogHandler($this->container);
        $errorLogHandler->setException($exception);
        $errorLogHandler->handle();
    }
}
