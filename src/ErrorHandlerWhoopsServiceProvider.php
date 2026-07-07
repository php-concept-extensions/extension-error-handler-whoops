<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use Whoops\Handler\Handler;
use Whoops\Run as Whoops;

final class ErrorHandlerWhoopsServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'error-handler-whoops';

    /**
     * @param list<Handler> $handlers Ordered Whoops handlers: report first, render last.
     */
    public function __construct(
        private readonly array $handlers = [],
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
        if ($this->handlers === []) {
            return;
        }

        $container = $this->getContainer();

        /** @var Whoops|null $whoops */
        $whoops = ContainerResolver::tryGet($container, Whoops::class);
        if ($whoops === null) {
            return;
        }

        $whoops->clearHandlers();

        foreach ($this->handlers as $handler) {
            $whoops->appendHandler($handler);
        }

        $whoops->register();

        EventDispatcherResolver::optional($container)?->dispatch(new ExtensionAwakened(
            extensionName: self::EXTENSION_NAME,
            anchorId: Whoops::class,
        ));
    }
}
