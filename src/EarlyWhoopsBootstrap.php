<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Concept\Extensions\ErrorHandlerWhoops\Handlers\EarlyBootstrapFallbackHandler;
use Concept\Extensions\ErrorHandlerWhoops\Handlers\PhpErrorLogHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;

final class EarlyWhoopsBootstrap
{
    public static function register(string $root): Whoops
    {
        if (ob_get_level() === 0) {
            ob_start();
        }

        $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        $whoops = new Whoops();

        if ($debug) {
            $whoops->pushHandler(new PrettyPageHandler());
        } elseif (PHP_SAPI === 'cli') {
            $whoops->pushHandler(new PlainTextHandler());
        } else {
            $whoops->pushHandler(new EarlyBootstrapFallbackHandler($root));
        }

        $whoops->pushHandler(new PhpErrorLogHandler());
        $whoops->register();

        return $whoops;
    }
}
