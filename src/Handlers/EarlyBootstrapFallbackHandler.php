<?php declare(strict_types=1);

namespace Concept\Extensions\ErrorHandlerWhoops\Handlers;

use Concept\Extensions\Http\Protocol\HttpStatusCode;
use Whoops\Handler\Handler;

final class EarlyBootstrapFallbackHandler extends Handler
{
    private const string FALLBACK_FILE_FORMAT = '%s/%s.php';

    public function __construct(private readonly ?string $fallbackPath = null) {}

    public function handle(): int
    {
        if (!headers_sent()) {
            http_response_code(HttpStatusCode::INTERNAL_SERVER_ERROR);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($this->fallbackPath === null) {
            return Handler::QUIT;
        }

        $fallbackFile = sprintf(
            self::FALLBACK_FILE_FORMAT,
            $this->fallbackPath,
            HttpStatusCode::INTERNAL_SERVER_ERROR,
        );
        if (file_exists($fallbackFile)) {
            include $fallbackFile;
        }

        return Handler::QUIT;
    }
}
