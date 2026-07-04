<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Handlers;

use Concept\Extensions\ErrorHandlerWhoops\Logging\PhpErrorLogWriter;
use Throwable;
use Whoops\Handler\Handler;

final class PhpErrorLogHandler extends Handler
{
    public function __construct(private readonly PhpErrorLogWriter $writer = new PhpErrorLogWriter()) {}

    public function handle(): int
    {
        try {
            $this->writer->write($this->getException(), bootstrap: true);
        } catch (Throwable) {
        }

        return Handler::DONE;
    }
}
