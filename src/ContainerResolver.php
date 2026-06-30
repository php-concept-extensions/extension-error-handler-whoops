<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Psr\Container\ContainerInterface;
use Throwable;

final class ContainerResolver
{
    /**
     * @template T
     * @param class-string<T> $id
     * @return T|null
     */
    public static function tryGet(ContainerInterface $container, string $id): mixed
    {
        try {
            return $container->get($id);
        } catch (Throwable) {
            return null;
        }
    }
}
