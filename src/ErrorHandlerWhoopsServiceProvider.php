<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Concept\Extensions\ErrorHandlerWhoops\Handlers\EarlyBootstrapFallbackHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\ErrorLogHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\PhpErrorLogHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\ProductionErrorHandler;
use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use Concept\Extensions\Http\Requests\RequestFormat;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;

final class ErrorHandlerWhoopsServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'error-handler-whoops';

    public function __construct(
        private readonly string $root,
        private readonly bool $debug,
        private readonly string $errorsFallbackPath,
    ) {}

    public function provides(string $id): bool
    {
        return $id === Whoops::class;
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
        $container = $this->getContainer();

        /** @var Whoops|null $whoops */
        $whoops = ContainerResolver::tryGet($container, Whoops::class);
        if ($whoops === null) {
            return;
        }

        try {
            $whoops->clearHandlers();

            $whoops->appendHandler(function(Throwable $exception): int {
                $handler = new PhpErrorLogHandler();
                $handler->setException($exception);

                return $handler->handle();
            });

            $whoops->appendHandler(function(Throwable $exception) use ($container): int {
                $handler = new ErrorLogHandler($container);
                $handler->setException($exception);

                return $handler->handle();
            });

            $this->registerRenderHandlers($container, $whoops);

            $whoops->register();
        } catch (Throwable) {
            $this->restoreEarlyHandlers($container, $whoops);
        }

        EventDispatcherResolver::optional($container)?->dispatch(new ExtensionAwakened(
            extensionName: self::EXTENSION_NAME,
            anchorId: Whoops::class,
        ));
    }

    private function registerRenderHandlers(ContainerInterface $container, Whoops $whoops): void
    {
        /** @var ServerRequestInterface|null $request */
        $request = ContainerResolver::tryGet($container, ServerRequestInterface::class);
        /** @var RequestFormat|null $requestFormat */
        $requestFormat = ContainerResolver::tryGet($container, RequestFormat::class);

        if ($request !== null && $requestFormat !== null && $requestFormat->expectsJson($request)) {
            $whoops->appendHandler(new JsonResponseHandler());

            return;
        }

        $this->registerHandlers($container, $whoops);
    }

    private function registerHandlers(ContainerInterface $container, Whoops $whoops): void
    {
        if ($this->isCli()) {
            $whoops->appendHandler(new PlainTextHandler());

            return;
        }

        if ($this->debug) {
            $whoops->appendHandler(new PrettyPageHandler());

            return;
        }

        $whoops->appendHandler(function(Throwable $exception) use ($container): int {
            $handler = new ProductionErrorHandler($container, $this->errorsFallbackPath);
            $handler->setException($exception);

            return $handler->handle();
        });
    }

    private function restoreEarlyHandlers(ContainerInterface $container, Whoops $whoops): void
    {
        $whoops->clearHandlers();

        if (!$this->isCli()) {
            $whoops->appendHandler(new EarlyBootstrapFallbackHandler($this->root));
        } elseif ($this->debug) {
            $whoops->appendHandler(new PrettyPageHandler());
        } else {
            $whoops->appendHandler(new PlainTextHandler());
        }

        $whoops->pushHandler(new PhpErrorLogHandler());
        $whoops->register();
    }

    private function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }
}
