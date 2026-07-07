<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Whoops\Handler\Handler;
use Whoops\Run as Whoops;

final class EarlyWhoopsServiceProvider
{
    public static function register(Handler ...$handlers): Whoops
    {
        if (ob_get_level() === 0) {
            ob_start();
        }

        $whoops = new Whoops();

        foreach ($handlers as $handler) {
            $whoops->pushHandler($handler);
        }

        $whoops->register();

        return $whoops;
    }
}
