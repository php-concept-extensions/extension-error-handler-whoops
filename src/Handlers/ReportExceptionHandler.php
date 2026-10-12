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
     * @param null|(Closure(): string) $uriFactory
     */
    public function __construct(
        private readonly Closure $reporterFactory,
        private readonly ?Closure $uriFactory = null,
    ) {}

    public function handle(): int
    {
        try {
            ($this->reporterFactory)()->report(
                $this->getException(),
                $this->resolveUri(),
            );
        } catch (Throwable) {
        }

        return Handler::DONE;
    }

    private function resolveUri(): string
    {
        if ($this->uriFactory !== null) {
            return ($this->uriFactory)();
        }

        if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            return $_SERVER['REQUEST_URI'];
        }

        return '';
    }
}
