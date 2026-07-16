<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface HttpErrorRendererInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $errors
     */
    public function render(
        ServerRequestInterface $request,
        int $code,
        ?string $message = null,
        array $data = [],
        array $errors = [],
    ): ResponseInterface;
}
