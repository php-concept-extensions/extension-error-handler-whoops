<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Concept\Extensions\ErrorHandlerWhoops\Contracts\ExceptionReporterInterface;
use Concept\Extensions\ErrorHandlerWhoops\Contracts\HttpErrorRendererInterface;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\EarlyBootstrapFallbackHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\HttpErrorRendererHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\PhpErrorLogHandler;
use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Throwable;
use Whoops\Handler\Handler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;

final class ErrorHandlerWhoopsServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'error-handler-whoops';

    public function __construct(
        private readonly bool $debug = false,
        private readonly ?string $errorsFallbackPath = null,
        private readonly ?ExceptionReporterInterface $exceptionReporter = null,
        private readonly ?HttpErrorRendererInterface $httpErrorRenderer = null,
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
            $this->registerAwakeHandlers($whoops, $container);
        } catch (Throwable) {
            $this->registerEarlyHandlers($whoops);
        }

        EventDispatcherResolver::optional($container)?->dispatch(new ExtensionAwakened(
            extensionName: self::EXTENSION_NAME,
            anchorId: Whoops::class,
        ));
    }

    private function registerAwakeHandlers(Whoops $whoops, ContainerInterface $container): void
    {
        $whoops->clearHandlers();

        if ($this->exceptionReporter !== null) {
            $reporter = $this->exceptionReporter;
            $whoops->appendHandler(function(Throwable $exception) use ($reporter): int {
                $reporter->report($exception);

                return Handler::DONE;
            });
        }

        if (!$this->isCli() && $this->httpErrorRenderer !== null) {
            $whoops->appendHandler(new HttpErrorRendererHandler($this->httpErrorRenderer, $container));
        } elseif ($this->debug) {
            $whoops->appendHandler($this->isCli() ? new PlainTextHandler() : new PrettyPageHandler());
        }

        $whoops->register();
    }

    private function registerEarlyHandlers(Whoops $whoops): void
    {
        $whoops->clearHandlers();

        if (!$this->isCli()) {
            $whoops->appendHandler(new EarlyBootstrapFallbackHandler($this->errorsFallbackPath));
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
