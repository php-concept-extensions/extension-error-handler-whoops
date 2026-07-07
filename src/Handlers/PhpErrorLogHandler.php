<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Handlers;

use Concept\Extensions\ErrorHandlerWhoops\Contracts\ExceptionReporterInterface;
use Throwable;
use Whoops\Handler\Handler;

final class PhpErrorLogHandler extends Handler
{
    public function __construct(private readonly ExceptionReporterInterface $phpErrorLogWriter) {}

    public function handle(): int
    {
        try {
            $this->phpErrorLogWriter->report($this->getException());
        } catch (Throwable) {
        }

        return Handler::DONE;
    }
}
