<?php

/**
 * Front controller.
 *
 * This is the only PHP file in the web root, so anything else in the project
 * (configuration, tests, migrations) is unreachable over HTTP by construction.
 */

declare(strict_types=1);

use App\Http\Kernel;
use App\Http\Response;

// The project root sits one level above the document root.
$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

// Boot failures are handled here rather than inside the kernel, because a
// failure to connect to the database happens before routing can take over. A
// misconfigured deployment must report a clean 503, not a stack trace.
try {
    $kernel = (new Kernel($basePath))->boot();
} catch (Throwable $throwable) {
    error_log('[BOOT] ' . $throwable::class . ': ' . $throwable->getMessage());

    $debug = getenv('APP_DEBUG') === 'true';

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<title>服务暂时不可用</title></head><body>'
        . '<h1>服务暂时不可用</h1><p>站点初始化失败，请稍后再试。</p>';

    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $throwable, ENT_QUOTES, 'UTF-8') . '</pre>';
    }

    echo '</body></html>';

    exit;
}

/** @var callable(App\Http\Router, Kernel): void $routes */
$routes = require $basePath . '/routes/web.php';

$kernel->handle($routes);
