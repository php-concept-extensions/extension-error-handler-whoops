<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops;

use Concept\Extensions\ErrorHandlerWhoops\Handlers\EarlyBootstrapFallbackHandler;
use Whoops\Handler\Handler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;

final class EarlyWhoopsBootstrap
{
    public static function register(?string $errorsFallbackFilePath, ?Handler $logHandler = null): Whoops
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
            if (!is_null($errorsFallbackFilePath)) {
                $whoops->pushHandler(new EarlyBootstrapFallbackHandler($errorsFallbackFilePath));
            }
        }

        if ($logHandler !== null) {
            $whoops->pushHandler($logHandler);
        }

        $whoops->register();

        return $whoops;
    }
}
