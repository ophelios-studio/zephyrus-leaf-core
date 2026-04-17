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

        $builder = new StaticSiteBuilder($application, $router);
        $builder->setOutputDirectory($outputDir);
        $builder->setPublicDirectory(ROOT_DIR . '/public');
        $builder->setBaseUrl('http://localhost');

        // Multi-locale configuration.
        $localizationConfig = $this->app->getConfig()->localization;
        $supportedLocales = $localizationConfig->supportedLocales;
        $isMultiLocale = count($supportedLocales) > 1;
        if ($isMultiLocale) {
            $builder->setLocales($supportedLocales, $localizationConfig->locale);
            $translationExt = $this->app->getTranslationExtension();
            if ($translationExt !== null) {
                $builder->setTranslationExtension($translationExt);
            }
        }

        // Discover doc content paths (section/slug from markdown files).
        $contentDir = ROOT_DIR . '/' . ltrim($leafConfig->contentPath, '/');
        if (is_dir($contentDir)) {
            $sections = array_filter(
                scandir($contentDir),
                fn($f) => $f !== '.' && $f !== '..' && is_dir($contentDir . '/' . $f),
            );
            foreach ($sections as $section) {
                foreach (glob($contentDir . '/' . $section . '/*.md') as $file) {
                    $slug = basename($file, '.md');
                    $builder->addPath("/{$section}/{$slug}");
                }
            }
        }

        // Caller-provided paths and exclusions.
        if (!empty($this->additionalPaths)) {
            $builder->addPaths($this->additionalPaths);
        }
        $builder->excludePatterns(array_merge(
            ['#^/search\.json$#', '#^/$#'],
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

        // Generate search index.
        $searchIndexBuilder = new SearchIndexBuilder(
            $contentDir,
            new MarkdownParser(),
            $leafConfig->baseUrl,
        );
        $index = $searchIndexBuilder->build();
        file_put_contents($outputDir . '/search.json', json_encode($index, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        echo "  -> " . count($index) . " pages indexed" . PHP_EOL;

        // Single-locale root redirect to first doc page.
        if (!$isMultiLocale) {
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

        // Run project-specific post-build callbacks.
        foreach ($this->postBuildCallbacks as $callback) {
            $callback($result, $outputDir);
        }

        echo PHP_EOL . "Build complete!" . PHP_EOL;
        return 0;
    }
}
