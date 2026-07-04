<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Handlers;

use Concept\Extensions\ErrorHandlerWhoops\ContainerResolver;
use Concept\Extensions\ErrorHandlerWhoops\Contracts\HttpErrorRendererInterface;
use Concept\Extensions\Http\Protocol\HttpStatusCode;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Whoops\Handler\Handler;

final class HttpErrorRendererHandler extends Handler
{
    public function __construct(
        private readonly HttpErrorRendererInterface $httpErrorRenderer,
        private readonly ContainerInterface $container,
    ) {}

    public function handle(): int
    {
        try {
            /** @var ServerRequestInterface|null $request */
            $request = ContainerResolver::tryGet($this->container, ServerRequestInterface::class);
            if ($request === null) {
                return Handler::NEXT;
            }

            $exception = $this->getException();
            $response = $this->httpErrorRenderer->render(
                $request,
                $this->resolveStatusCode($exception),
                $exception->getMessage(),
            );
            $this->emit($response);
        } catch (Throwable) {
            return Handler::NEXT;
        }

        return Handler::QUIT;
    }

    private function resolveStatusCode(Throwable $exception): int
    {
        $code = $exception->getCode();

        if ($code >= 400 && $code <= 599) {
            return $code;
        }

        return HttpStatusCode::INTERNAL_SERVER_ERROR;
    }

    private function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }

        echo (string) $response->getBody();
    }
}
