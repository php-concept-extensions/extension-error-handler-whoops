<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Closure;
use Concept\Core\Container\ContainerDependency;
use Concept\Extensions\ErrorHandlerWhoops\Contracts\ExceptionReporterInterface;
use Concept\Extensions\ErrorHandlerWhoops\Contracts\HttpErrorRendererInterface;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\RenderHttpErrorHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\ReportExceptionHandler;
use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use Psr\Http\Message\ServerRequestInterface;
use Whoops\Handler\HandlerInterface;
use Whoops\Handler\PlainTextHandler;
use Whoops\Run as Whoops;

final class ErrorHandlerWhoopsServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'error-handler-whoops';

    /** @var Closure(): ExceptionReporterInterface */
    private Closure $sharedExceptionReporterFactory;

    /** @var Closure(): HttpErrorRendererInterface */
    private Closure $sharedHttpErrorRendererFactory;

    /**
     * @param Closure(): ExceptionReporterInterface $exceptionReporterFactory
     * @param Closure(): HttpErrorRendererInterface $httpErrorRendererFactory
     * @param null|(Closure(): ?HandlerInterface) $debugHttpHandlerFactory
     */
    public function __construct(
        private readonly Closure $exceptionReporterFactory,
        private readonly Closure $httpErrorRendererFactory,
        private readonly ?Closure $debugHttpHandlerFactory = null,
    ) {
        $reporter = null;
        $this->sharedExceptionReporterFactory = function() use (&$reporter): ExceptionReporterInterface {
            return $reporter ??= ($this->exceptionReporterFactory)();
        };

        $renderer = null;
        $this->sharedHttpErrorRendererFactory = function() use (&$renderer): HttpErrorRendererInterface {
            return $renderer ??= ($this->httpErrorRendererFactory)();
        };
    }

    public function provides(string $id): bool
    {
        return $id === Whoops::class
            || $id === ExceptionReporterInterface::class
            || $id === HttpErrorRendererInterface::class;
    }

    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(ExceptionReporterInterface::class, $this->sharedExceptionReporterFactory)->setShared(true);
        $container->add(HttpErrorRendererInterface::class, $this->sharedHttpErrorRendererFactory)->setShared(true);
    }

    public function boot(): void
    {
        $container = $this->getContainer();

        /** @var Whoops|null $whoops */
        $whoops = ContainerResolver::tryGet($container, Whoops::class);
        if ($whoops === null) {
            return;
        }

        $whoops->clearHandlers();

        foreach ($this->buildHandlers() as $handler) {
            $whoops->appendHandler($handler);
        }

        $whoops->register();

        EventDispatcherResolver::optional($container)?->dispatch(new ExtensionAwakened(
            extensionName: self::EXTENSION_NAME,
            anchorId: Whoops::class,
        ));
    }

    /**
     * @return list<HandlerInterface>
     */
    private function buildHandlers(): array
    {
        $container = $this->getContainer();
        $uriFactory = static function() use ($container): string {
            if ($container->has(ServerRequestInterface::class)) {
                return ContainerDependency::get($container, ServerRequestInterface::class)
                    ->getUri()
                    ->getPath();
            }

            if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
                return $_SERVER['REQUEST_URI'];
            }

            return '';
        };

        $handlers = [
            new ReportExceptionHandler($this->sharedExceptionReporterFactory, $uriFactory),
        ];

        if (PHP_SAPI === 'cli') {
            $handlers[] = new PlainTextHandler();

            return $handlers;
        }

        $debugHttpHandlerFactory = $this->debugHttpHandlerFactory;
        if ($debugHttpHandlerFactory !== null) {
            $debugHandler = $debugHttpHandlerFactory();
            if ($debugHandler !== null) {
                $handlers[] = $debugHandler;

                return $handlers;
            }
        }

        $handlers[] = new RenderHttpErrorHandler(
            $this->sharedHttpErrorRendererFactory,
            static function() use ($container): ?ServerRequestInterface {
                if (!$container->has(ServerRequestInterface::class)) {
                    return null;
                }

                return ContainerDependency::get($container, ServerRequestInterface::class);
            },
        );

        return $handlers;
    }
}
