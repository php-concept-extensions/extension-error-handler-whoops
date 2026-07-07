<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Contracts;

use Throwable;

interface ExceptionReporterInterface
{
    public function report(Throwable $exception, string $uri = '', bool $bootstrap = false): void;
}
