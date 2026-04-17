<?php

declare(strict_types=1);

namespace Leaf;

use Leaf\Localization\TranslationLatteExtension;
use Zephyrus\Core\Application;
use Zephyrus\Http\Request;
use Zephyrus\Routing\Router;

/**
 * Builds a static HTML site from a running Zephyrus application.
 *
 * Collects all non-parameterized GET routes from the Router, dispatches each
 * through the full Application stack (middleware, events, controllers), and
 * writes the rendered HTML to an output directory.
 *
 * Usage:
 *
 *   $builder = new StaticSiteBuilder($app, $router);
 *   $builder->setOutputDirectory(ROOT_DIR . '/dist');
 *   $builder->setPublicDirectory(ROOT_DIR . '/public');
 *   $builder->addPaths($dynamicPaths);
 *   $result = $builder->build();
 */
final class StaticSiteBuilder
{
    private string $outputDirectory = '';
    private string $publicDirectory = '';
    private string $baseUrl = 'http://localhost';

    /** @var list<string> */
    private array $additionalPaths = [];

    /** @var list<string> */
    private array $excludePatterns = [];

    /** @var list<string> */
    private array $assetExcludes = ['index.php', '.htaccess'];

    /** @var list<string> */
    private array $locales = [];

    private string $defaultLocale = 'en';

    private ?TranslationLatteExtension $translationExtension = null;

    public function __construct(
        private readonly Application $application,
        private readonly Router $router,
    ) {
    }

    public function setOutputDirectory(string $path): void
    {
        $this->outputDirectory = rtrim($path, '/\\');
    }

    public function setPublicDirectory(string $path): void
    {
        $this->publicDirectory = rtrim($path, '/\\');
    }

    public function setBaseUrl(string $url): void
    {
        $this->baseUrl = rtrim($url, '/');
    }

    /**
     * @param list<string> $paths
     */
    public function addPaths(array $paths): void
    {
        foreach ($paths as $path) {
            $this->additionalPaths[] = '/' . ltrim($path, '/');
        }
    }

    public function addPath(string $path): void
    {
        $this->additionalPaths[] = '/' . ltrim($path, '/');
    }

    /**
     * @param list<string> $patterns Regex patterns (e.g. '#^/api/#').
     */
    public function excludePatterns(array $patterns): void
    {
        $this->excludePatterns = $patterns;
    }

    /**
     * @param list<string> $filenames
     */
    public function setAssetExcludes(array $filenames): void
    {
        $this->assetExcludes = $filenames;
    }

    /**
     * @param list<string> $locales
     */
    public function setLocales(array $locales, string $defaultLocale): void
    {
        $this->locales = $locales;
        $this->defaultLocale = $defaultLocale;
    }

    public function setTranslationExtension(TranslationLatteExtension $extension): void
    {
        $this->translationExtension = $extension;
    }

    public function build(): StaticBuildResult
    {
        if ($this->outputDirectory === '') {
            throw new \RuntimeException('Output directory must be set before building.');
        }

        if ($this->locales !== [] && count($this->locales) > 1) {
            return $this->buildMultiLocale();
        }

        $result = $this->buildSingleLocale($this->outputDirectory);

        if ($this->publicDirectory !== '' && is_dir($this->publicDirectory)) {
            $this->copyAssets();
        }

        return $result;
    }

    private function buildSingleLocale(string $outputDir): StaticBuildResult
    {
        $startTime = hrtime(true);
        $paths = $this->collectPaths();
        $pagesBuilt = 0;
        $errors = [];
        $builtPages = [];

        $originalOutputDir = $this->outputDirectory;
        $this->outputDirectory = $outputDir;

        foreach ($paths as $path) {
            try {
                $request = Request::fromArray('GET', $this->baseUrl . $path);
                $response = $this->application->handle($request);

                if ($response->status >= 300 && $response->status < 400) {
                    continue;
                }

                if ($response->status !== 200) {
                    $errors[] = sprintf('%s returned HTTP %d', $path, $response->status);
                    continue;
                }

                $this->writePage($path, $response->body);
                $builtPages[] = $path;
                $pagesBuilt++;
            } catch (\Throwable $e) {
                $errors[] = sprintf('%s failed: %s', $path, $e->getMessage());
            }
        }

        $this->outputDirectory = $originalOutputDir;
        $elapsed = (hrtime(true) - $startTime) / 1_000_000;

        return new StaticBuildResult(
            pagesBuilt: $pagesBuilt,
            totalPaths: count($paths),
            elapsedMs: round($elapsed, 2),
            errors: $errors,
            outputDirectory: $outputDir,
            builtPages: $builtPages,
        );
    }

    private function buildMultiLocale(): StaticBuildResult
    {
        $startTime = hrtime(true);
        $totalPages = 0;
        $totalPaths = 0;
        $allErrors = [];
        $builtPages = [];

        foreach ($this->locales as $locale) {
            if ($this->translationExtension !== null) {
                $this->translationExtension->setCurrentLocale($locale);
            }

            $localeOutputDir = ($locale === $this->defaultLocale)
                ? $this->outputDirectory
                : $this->outputDirectory . '/' . $locale;
            $result = $this->buildSingleLocale($localeOutputDir);

            $totalPages += $result->pagesBuilt;
            $totalPaths += $result->totalPaths;
            if (empty($builtPages)) {
                $builtPages = $result->builtPages;
            }

            foreach ($result->errors as $error) {
                $allErrors[] = "[{$locale}] {$error}";
            }
        }

        if ($this->publicDirectory !== '' && is_dir($this->publicDirectory)) {
            $this->copyAssets();
        }

        $elapsed = (hrtime(true) - $startTime) / 1_000_000;

        return new StaticBuildResult(
            pagesBuilt: $totalPages,
            totalPaths: $totalPaths,
            elapsedMs: round($elapsed, 2),
            errors: $allErrors,
            outputDirectory: $this->outputDirectory,
            builtPages: $builtPages,
        );
    }


    /**
     * @return list<string>
     */
    private function collectPaths(): array
    {
        $paths = [];

        foreach ($this->router->routes()->all() as $route) {
            if ($route->method !== 'GET') {
                continue;
            }
            if (str_contains($route->path, '{')) {
                continue;
            }
            $paths[] = $route->path;
        }

        $paths = array_merge($paths, $this->additionalPaths);
        $paths = array_values(array_unique($paths));

        if ($this->excludePatterns !== []) {
            $paths = array_values(array_filter($paths, function (string $path): bool {
                foreach ($this->excludePatterns as $pattern) {
                    if (preg_match($pattern, $path)) {
                        return false;
                    }
                }
                return true;
            }));
        }

        sort($paths);
        return $paths;
    }

    private function writePage(string $path, string $body): void
    {
        $filePath = $this->outputDirectory . ($path === '/' ? '/index.html' : $path . '/index.html');
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($filePath, $body);
    }

    private function copyAssets(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->publicDirectory,
                \RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($this->publicDirectory) + 1);

            if (in_array(basename($relativePath), $this->assetExcludes, true)) {
                continue;
            }

            $targetPath = $this->outputDirectory . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                copy($item->getPathname(), $targetPath);
            }
        }
    }
}
