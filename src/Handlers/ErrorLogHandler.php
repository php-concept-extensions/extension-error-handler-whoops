<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Handlers;

use Concept\Extensions\LoggerMonolog\Contracts\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Whoops\Handler\Handler;

final class ErrorLogHandler extends Handler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?ServerRequestInterface $request = null,
    ) {}

    public function handle(): int
    {
        try {
            $this->logger->exception($this->getException(), $this->resolveUri());
        } catch (Throwable) {
        }

        return Handler::DONE;
    }

    private function resolveUri(): string
    {
        return $this->request?->getUri()->getPath() ?? '';
    }
}
