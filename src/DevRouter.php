<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Development server router for PHP's built-in web server.
 *
 * Handles:
 * - Static file serving from the public directory
 * - Live-reload endpoint (/__dev/reload) that returns a file change hash
 * - Fallthrough to the Zephyrus application for all other requests
 *
 * Usage in bin/router.php:
 *
 *   require __DIR__ . '/../vendor/autoload.php';
 *   (new \Leaf\DevRouter(__DIR__ . '/..'))->handle();
 */
final class DevRouter
{
    private readonly FileWatcher $watcher;

    public function __construct(
        private readonly string $projectRoot,
    ) {
        $this->watcher = new FileWatcher([
            $this->projectRoot . '/content',
            $this->projectRoot . '/app/Views',
            $this->projectRoot . '/public/assets/css',
            $this->projectRoot . '/public/assets/js',
            $this->projectRoot . '/src',
        ]);
    }

    /**
     * Route the current request. Returns true if handled, false to let
     * PHP's built-in server serve a static file.
     */
    public function handle(): bool
    {
        $uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        // Live-reload endpoint.
        if ($uri === '/__dev/reload') {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache');
            echo json_encode(['hash' => $this->watcher->hash()]);
            return true;
        }

        // Locale prefix stripping: /en/... or /fr/... → strip and store locale.
        if (preg_match('#^/([a-z]{2})(/.*)?$#', $uri, $matches)) {
            $possibleLocale = $matches[1];
            $remainder = $matches[2] ?? '/';
            // Only strip if it looks like a locale (2-letter code) and the path
            // without prefix doesn't map to a static file.
            $publicPath = $this->projectRoot . '/public' . $uri;
            if (!is_file($publicPath)) {
                $_SERVER['LEAF_LOCALE'] = $possibleLocale;
                $_SERVER['REQUEST_URI'] = $remainder;
                $uri = $remainder;
            }
        }

        // Static file from public/.
        $publicPath = $this->projectRoot . '/public' . $uri;
        if ($uri !== '/' && is_file($publicPath)) {
            return false; // Let PHP's built-in server handle it.
        }

        // Fall through to the Zephyrus application.
        require $this->projectRoot . '/public/index.php';
        return true;
    }
}
