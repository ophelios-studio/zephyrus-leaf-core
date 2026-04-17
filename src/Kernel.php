<?php

declare(strict_types=1);

namespace Leaf;

use Dotenv\Dotenv;
use Leaf\Config\LeafConfig;
use Leaf\Content\ContentLoader;
use Leaf\Content\MarkdownParser;
use Leaf\Content\SearchIndexBuilder;
use Leaf\Localization\TranslationLatteExtension;
use Zephyrus\Core\ApplicationBuilder;
use Zephyrus\Core\Config\Configuration;
use Zephyrus\Http\Request;
use Zephyrus\Http\Response;
use Zephyrus\Localization\JsonLocaleLoader;
use Zephyrus\Localization\Translator;
use Zephyrus\Rendering\LatteEngine;
use Zephyrus\Rendering\RenderConfig;
use Zephyrus\Routing\Exception\RouteNotFoundException;
use Zephyrus\Routing\Router;

abstract class Kernel
{
    protected Configuration $config;
    protected LeafConfig $leafConfig;
    protected LatteEngine $renderEngine;
    protected MarkdownParser $markdownParser;
    protected ContentLoader $contentLoader;
    protected SearchIndexBuilder $searchIndexBuilder;
    protected ?TranslationLatteExtension $translationExtension = null;
    protected ?Translator $translator = null;

    public function __construct()
    {
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', dirname(__DIR__, 3));
        }
        $this->boot();
    }

    public function run(): void
    {
        $localeOverride = $_SERVER['LEAF_LOCALE'] ?? null;
        if ($localeOverride !== null && $this->translationExtension !== null) {
            $this->translationExtension->setCurrentLocale($localeOverride);
        }

        [$app] = $this->buildApplication();
        $request = Request::fromGlobals();
        $response = $app->handle($request);
        $response->send();
    }

    /**
     * @return array{0: \Zephyrus\Core\Application, 1: Router}
     */
    public function buildForStaticGeneration(): array
    {
        return $this->buildApplication();
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function getLeafConfig(): LeafConfig
    {
        return $this->leafConfig;
    }

    public function getRenderEngine(): LatteEngine
    {
        return $this->renderEngine;
    }

    public function getContentLoader(): ContentLoader
    {
        return $this->contentLoader;
    }

    public function getTranslationExtension(): ?TranslationLatteExtension
    {
        return $this->translationExtension;
    }

    public function getTranslator(): ?Translator
    {
        return $this->translator;
    }

    /**
     * Register application controllers. Override to customize controller
     * discovery or add controllers manually.
     */
    protected function registerControllers(Router $router): Router
    {
        return $router->discoverControllers(
            namespace: 'App\\Controllers',
            directory: ROOT_DIR . '/app/Controllers',
        );
    }

    /**
     * Create a controller instance. Override to provide dependency injection
     * for controllers that need services like ContentLoader, SearchIndexBuilder, etc.
     */
    protected function createController(string $class): object
    {
        return new $class();
    }

    /**
     * @return array{0: \Zephyrus\Core\Application, 1: Router}
     */
    private function buildApplication(): array
    {
        $router = $this->registerControllers(new Router());
        $renderEngine = $this->renderEngine;

        $app = ApplicationBuilder::create()
            ->withConfiguration($this->config, basePath: ROOT_DIR)
            ->withRouter($router)
            ->withControllerFactory(function (string $class) use ($renderEngine): object {
                $controller = $this->createController($class);
                if (method_exists($controller, 'setRenderEngine')) {
                    $controller->setRenderEngine($renderEngine);
                }
                return $controller;
            })
            ->withExceptionHandler(RouteNotFoundException::class, function (\Throwable $e, ?Request $r) use ($renderEngine): Response {
                $html = $renderEngine->render('404', [
                    'title' => 'Page Not Found',
                ]);
                return Response::html($html, 404);
            })
            ->build();

        return [$app, $router];
    }

    private function boot(): void
    {
        Dotenv::createImmutable(ROOT_DIR)->safeLoad();

        $this->config = Configuration::fromYamlFile(ROOT_DIR . '/config.yml', [
            'render' => RenderConfig::class,
            'leaf' => LeafConfig::class,
        ]);

        /** @var LeafConfig $leafConfig */
        $leafConfig = $this->config->section('leaf');
        $this->leafConfig = $leafConfig ?? LeafConfig::fromArray([]);

        /** @var RenderConfig $renderConfig */
        $renderConfig = $this->config->section('render') ?? RenderConfig::fromArray([]);
        $this->renderEngine = $renderConfig->createEngine(ROOT_DIR);
        $this->renderEngine->addExtension(new LeafLatteExtension($this->leafConfig));

        $contentDir = ROOT_DIR . '/' . ltrim($this->leafConfig->contentPath, '/');
        $this->markdownParser = new MarkdownParser();
        $this->contentLoader = new ContentLoader($contentDir, $this->markdownParser, $this->leafConfig);
        $this->searchIndexBuilder = new SearchIndexBuilder($contentDir, $this->markdownParser);

        $localizationConfig = $this->config->localization;
        if ($localizationConfig->localePath !== null && $localizationConfig->localePath !== '') {
            $localePath = ROOT_DIR . '/' . ltrim($localizationConfig->localePath, '/');
            $loader = new JsonLocaleLoader($localePath);
            $this->translator = new Translator($loader, $localizationConfig->locale);
            $this->translationExtension = new TranslationLatteExtension(
                $this->translator,
                $localizationConfig->locale,
                $localizationConfig->supportedLocales,
            );
            $this->renderEngine->addExtension($this->translationExtension);
        }
    }
}
