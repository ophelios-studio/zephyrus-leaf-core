<?php

declare(strict_types=1);

namespace Leaf;

use Leaf\Content\MarkdownParser;
use Leaf\Content\SearchIndexBuilder;
use Leaf\Seo\RobotsGenerator;
use Leaf\Seo\SitemapGenerator;

class BuildCommand
{
    /** @var list<callable(StaticBuildResult, string): void> */
    private array $postBuildCallbacks = [];

    /** @var list<string> */
    private array $additionalPaths = [];

    /** @var list<string> */
    private array $excludePatterns = [];

    public function __construct(
        private readonly Kernel $app,
    ) {
    }

    /**
     * Register a callback to run after the standard build pipeline.
     * Receives the build result and output directory.
     */
    public function onPostBuild(callable $callback): void
    {
        $this->postBuildCallbacks[] = $callback;
    }

    /**
     * Add extra paths for parameterized routes (e.g. blog posts).
     *
     * @param list<string> $paths
     */
    public function addPaths(array $paths): void
    {
        $this->additionalPaths = array_merge($this->additionalPaths, $paths);
    }

    /**
     * Add regex patterns to exclude from the build.
     *
     * @param list<string> $patterns
     */
    public function excludePatterns(array $patterns): void
    {
        $this->excludePatterns = array_merge($this->excludePatterns, $patterns);
    }

    public function run(): int
    {
        echo "Building static site..." . PHP_EOL;

        $leafConfig = $this->app->getLeafConfig();
        $outputDir = ROOT_DIR . '/' . ltrim($leafConfig->outputPath, '/');

        [$application, $router] = $this->app->buildForStaticGeneration();

        // Detect whether the application registers its own `GET /` route (a landing page).
        // When present, the static builder should render it normally instead of writing
        // a docs redirect at the root.
        $hasRootRoute = false;
        foreach ($router->routes()->all() as $route) {
            if ($route->method === 'GET' && $route->path === '/') {
                $hasRootRoute = true;
                break;
            }
        }

        $builder = new StaticSiteBuilder($application, $router);
        $builder->setOutputDirectory($outputDir);
        $builder->setPublicDirectory(ROOT_DIR . '/public');
        $builder->setBaseUrl('http://localhost');

        // Multi-locale configuration.
        $localizationConfig = $this->app->getConfig()->localization;
        $supportedLocales = $localizationConfig->supportedLocales;
        $defaultLocale = $localizationConfig->locale;
        $isMultiLocale = count($supportedLocales) > 1;
        if ($isMultiLocale) {
            $builder->setLocales($supportedLocales, $defaultLocale);
            $translationExt = $this->app->getTranslationExtension();
            if ($translationExt !== null) {
                $builder->setTranslationExtension($translationExt);
            }
            // Thread the ContentLoader in so per-locale file resolution happens.
            $builder->setContentLoader($this->app->getContentLoader());
        }

        // Discover doc content paths. Single-locale: straight scan of
        // content/*/* . Multi-locale: compute the set per locale (default +
        // locale overrides) so a locale-only page doesn't 404 other locales.
        $contentDir = ROOT_DIR . '/' . ltrim($leafConfig->contentPath, '/');
        if (is_dir($contentDir)) {
            if ($isMultiLocale) {
                $byLocale = $this->discoverContentPathsByLocale($contentDir, $supportedLocales, $defaultLocale);
                $builder->setLocaleAdditionalPaths($byLocale);
            } else {
                foreach ($this->discoverContentPathsByLocale($contentDir, [], '') as $paths) {
                    foreach ($paths as $path) {
                        $builder->addPath($path);
                    }
                }
            }
        }

        // Caller-provided paths and exclusions.
        if (!empty($this->additionalPaths)) {
            $builder->addPaths($this->additionalPaths);
        }
        // Exclude `/` from the builder only when the app has no landing route; otherwise
        // the builder would skip the custom landing and we'd ship just the docs redirect.
        $defaultExcludes = $hasRootRoute ? ['#^/search\.json$#'] : ['#^/search\.json$#', '#^/$#'];
        $builder->excludePatterns(array_merge(
            $defaultExcludes,
            $this->excludePatterns,
        ));

        // Build.
        $result = $builder->build();
        echo $result->summary() . PHP_EOL;

        if (!$result->isSuccessful()) {
            echo PHP_EOL . "Errors:" . PHP_EOL;
            foreach ($result->errors as $error) {
                echo "  - {$error}" . PHP_EOL;
            }
            return 1;
        }

        // Move /404/index.html to /404.html.
        $notFoundSource = $outputDir . '/404/index.html';
        if (is_file($notFoundSource)) {
            rename($notFoundSource, $outputDir . '/404.html');
            @rmdir($outputDir . '/404');
            echo "  -> 404.html created" . PHP_EOL;
        }

        // Generate search index. For multi-locale sites, produce a per-locale
        // search.json so each locale's client-side search only matches its
        // own pages (with English fallback content surfacing where appropriate).
        $searchIndexBuilder = new SearchIndexBuilder(
            $contentDir,
            new MarkdownParser(),
            $leafConfig->baseUrl,
        );
        if ($isMultiLocale) {
            foreach ($supportedLocales as $locale) {
                $searchIndexBuilder->setLocale($locale, $defaultLocale, $supportedLocales);
                $index = $searchIndexBuilder->build();
                $localeOutputDir = ($locale === $defaultLocale) ? $outputDir : $outputDir . '/' . $locale;
                if (!is_dir($localeOutputDir)) {
                    mkdir($localeOutputDir, 0o755, true);
                }
                file_put_contents(
                    $localeOutputDir . '/search.json',
                    json_encode($index, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
                );
                echo "  -> [{$locale}] " . count($index) . " pages indexed" . PHP_EOL;
            }
        } else {
            $index = $searchIndexBuilder->build();
            file_put_contents($outputDir . '/search.json', json_encode($index, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
            echo "  -> " . count($index) . " pages indexed" . PHP_EOL;
        }

        // Single-locale root redirect to first doc page (docs-only sites).
        // Skipped when the app has a landing route; its rendered HTML already sits at /.
        if (!$isMultiLocale && !$hasRootRoute) {
            $firstPageUrl = $this->app->getContentLoader()->getFirstPageUrl() . '/';
            $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="refresh" content="0;url={$firstPageUrl}">
                <title>Redirecting...</title>
            </head>
            <body>
                <p>Redirecting to <a href="{$firstPageUrl}">documentation</a>...</p>
            </body>
            </html>
            HTML;
            file_put_contents($outputDir . '/index.html', $html);
            echo "  -> root redirect created" . PHP_EOL;
        }

        // Generate sitemap and robots.txt if production URL is configured.
        $productionUrl = $leafConfig->productionUrl;
        if ($productionUrl !== '') {
            $sitemapGenerator = new SitemapGenerator($productionUrl, $outputDir);
            if ($isMultiLocale) {
                $sitemapGenerator->generateMultiLocale($result->builtPages, $supportedLocales, $localizationConfig->locale);
            } else {
                $sitemapGenerator->generate($result->builtPages);
            }
            echo "  -> sitemap.xml generated" . PHP_EOL;

            $robotsGenerator = new RobotsGenerator($outputDir);
            $robotsGenerator->generate($productionUrl . '/sitemap.xml');
            echo "  -> robots.txt generated" . PHP_EOL;
        }

        // Run config-declared post_build hooks (language-agnostic, run via
        // subprocess with the project root as cwd). Happens before the PHP
        // callbacks so hooks can prep artifacts that callbacks consume.
        //
        // Skipped when the process is driven by the leaf CLI binary (which
        // sets LEAF_SKIP_HOOKS=1 so it can execute hooks itself *after*
        // publishing dist/ back to the user's real project root).
        if (getenv('LEAF_SKIP_HOOKS') !== '1'
            && !$this->runPostBuildHooks($leafConfig->postBuild)) {
            return 1;
        }

        // Run project-specific post-build callbacks (PHP-level, Composer tier).
        foreach ($this->postBuildCallbacks as $callback) {
            $callback($result, $outputDir);
        }

        echo PHP_EOL . "Build complete!" . PHP_EOL;
        return 0;
    }

    /**
     * Compute the set of `/section/slug` paths each locale should build.
     *
     * - The default locale (or a single-locale site) gets every page under
     *   `content/<section>/<slug>.md` but NOT those that live only in a
     *   `content/<locale>/` override.
     * - Non-default locales get the default set PLUS any page added under
     *   their own `content/<locale>/` tree (including locale-only sections).
     *
     * In single-locale mode (empty $supportedLocales), returns a map with a
     * single empty-string key containing the default paths.
     *
     * @param list<string> $supportedLocales
     * @return array<string, list<string>>
     */
    private function discoverContentPathsByLocale(string $contentDir, array $supportedLocales, string $defaultLocale): array
    {
        $scan = function (string $root, array $exclude) use (&$scan): array {
            $found = [];
            if (!is_dir($root)) {
                return $found;
            }
            foreach (scandir($root) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (in_array($entry, $exclude, true)) {
                    continue;
                }
                $sectionDir = $root . '/' . $entry;
                if (!is_dir($sectionDir)) {
                    continue;
                }
                foreach (glob($sectionDir . '/*.md') ?: [] as $file) {
                    $slug = basename($file, '.md');
                    $found["/{$entry}/{$slug}"] = true;
                }
            }
            return $found;
        };

        $defaultPaths = $scan($contentDir, $supportedLocales);

        if ($supportedLocales === []) {
            return ['' => array_keys($defaultPaths)];
        }

        $byLocale = [];
        foreach ($supportedLocales as $locale) {
            $set = $defaultPaths; // start from default (fallback)
            if ($locale !== $defaultLocale) {
                $localePaths = $scan($contentDir . '/' . $locale, []);
                foreach ($localePaths as $p => $_) {
                    $set[$p] = true;
                }
            }
            $byLocale[$locale] = array_keys($set);
        }
        return $byLocale;
    }

    /**
     * Execute each configured post_build hook sequentially. Returns false on
     * the first failure (non-zero exit, missing executable, or proc_open
     * error). Stdio is inherited so hook output shows up in the build log.
     *
     * @param list<list<string>> $hooks
     */
    private function runPostBuildHooks(array $hooks): bool
    {
        if ($hooks === []) {
            return true;
        }

        echo PHP_EOL . "Running post_build hooks..." . PHP_EOL;
        $projectRoot = defined('ROOT_DIR') ? ROOT_DIR : getcwd();

        foreach ($hooks as $argv) {
            $display = implode(' ', $argv);
            echo "  -> {$display}" . PHP_EOL;

            $descriptors = [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ];
            $process = proc_open($argv, $descriptors, $pipes, $projectRoot);
            if (!is_resource($process)) {
                echo "  hook failed to start: {$display}" . PHP_EOL;
                return false;
            }
            $status = proc_close($process);
            if ($status !== 0) {
                echo "  hook exited {$status}: {$display}" . PHP_EOL;
                return false;
            }
        }

        return true;
    }
}
