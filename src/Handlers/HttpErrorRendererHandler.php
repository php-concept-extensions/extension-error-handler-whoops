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
    private const int MIN_ERROR_CODE = 400;
    private const int MAX_ERROR_CODE = 599;
    private const int DEFAULT_ERROR_CODE = HttpStatusCode::INTERNAL_SERVER_ERROR;

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
        if (method_exists($exception, 'getStatusCode')) {
            $code = $exception->getStatusCode();
        }

        if (!is_numeric($code) || $code < self::MIN_ERROR_CODE || $code > self::MAX_ERROR_CODE) {
            $code = self::DEFAULT_ERROR_CODE;
        }

        return (int) $code;
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
