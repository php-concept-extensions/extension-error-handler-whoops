<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Closure;
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
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Whoops\Handler\Handler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;

final class ErrorHandlerWhoopsServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'error-handler-whoops';
    private const string REQUEST_FORMAT_CLASS = 'Concept\Extensions\Http\Requests\RequestFormat';

    /**
     * @param Closure(): ExceptionReporterInterface|null $exceptionReporter
     * @param Closure(): HttpErrorRendererInterface|null $httpErrorRenderer
     */
    public function __construct(
        private readonly bool $debug = false,
        private readonly ?string $errorsFallbackPath = null,
        private readonly ?Closure $exceptionReporter = null,
        private readonly ?Closure $httpErrorRenderer = null,
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
            $exceptionReporter = $this->exceptionReporter;
            $whoops->appendHandler(function(Throwable $exception) use ($exceptionReporter): int {
                $exceptionReporter()->report($exception);

                return Handler::DONE;
            });
        }

        if ($this->isCli()) {
            $whoops->appendHandler(new PlainTextHandler());
            $whoops->register();

            return;
        }

        if ($this->httpErrorRenderer !== null && (!$this->debug || self::requestExpectsJson($container))) {
            $httpErrorRenderer = $this->httpErrorRenderer;
            $whoops->appendHandler(new HttpErrorRendererHandler($httpErrorRenderer, $container));
            $whoops->register();

            return;
        }

        if ($this->debug) {
            $whoops->appendHandler(new PrettyPageHandler());
        }

        $whoops->register();
    }

    private static function requestExpectsJson(ContainerInterface $container): bool
    {
        $request = ContainerResolver::tryGet($container, ServerRequestInterface::class);
        if (!$request instanceof ServerRequestInterface) {
            return false;
        }

        $requestFormat = ContainerResolver::tryGet($container, self::REQUEST_FORMAT_CLASS);
        if ($requestFormat === null || !method_exists($requestFormat, 'expectsJson')) {
            return false;
        }

        return $requestFormat->expectsJson($request);
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
