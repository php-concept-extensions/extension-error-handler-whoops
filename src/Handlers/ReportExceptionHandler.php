<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Handlers;

use Closure;
use Concept\Extensions\ErrorHandlerWhoops\Contracts\ExceptionReporterInterface;
use Throwable;
use Whoops\Handler\Handler;

final class ReportExceptionHandler extends Handler
{
    /**
     * @param Closure(): ExceptionReporterInterface $reporterFactory
     */
    public function __construct(
        private readonly Closure $reporterFactory,
    ) {}

    public function handle(): int
    {
        try {
            ($this->reporterFactory)()->report($this->getException());
        } catch (Throwable) {
        }

        return Handler::DONE;
    }
}
