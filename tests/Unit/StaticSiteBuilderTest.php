<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit;

use Leaf\StaticSiteBuilder;
use Leaf\Tests\Unit\Fixtures\PageController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zephyrus\Core\ApplicationBuilder;
use Zephyrus\Routing\Router;

final class StaticSiteBuilderTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/leaf-test-build-' . uniqid();
        mkdir($this->outputDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->outputDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->outputDir);
    }

    #[Test]
    public function aCustomPathIsBuiltForEveryLocaleAlongsideTheLocaleOwnPaths(): void
    {
        $builder = $this->builder();
        $builder->setLocales(['en', 'fr'], 'en');
        $builder->addPaths(['/about']);
        $builder->setLocaleAdditionalPaths([
            'en' => ['/guide'],
            'fr' => ['/guide', '/fr-only'],
        ]);

        $result = $builder->build();

        self::assertSame([], $result->errors);
        self::assertFileExists($this->outputDir . '/about/index.html');
        self::assertFileExists($this->outputDir . '/fr/about/index.html');
        self::assertFileExists($this->outputDir . '/guide/index.html');
        self::assertFileExists($this->outputDir . '/fr/fr-only/index.html');
    }

    #[Test]
    public function aPathOwnedByAnotherLocaleIsNotBuilt(): void
    {
        $builder = $this->builder();
        $builder->setLocales(['en', 'fr'], 'en');
        $builder->setLocaleAdditionalPaths([
            'en' => ['/guide'],
            'fr' => ['/fr-only'],
        ]);

        $builder->build();

        self::assertFileDoesNotExist($this->outputDir . '/fr-only/index.html');
        self::assertFileDoesNotExist($this->outputDir . '/fr/guide/index.html');
    }

    #[Test]
    public function theBuildResultListsTheBuiltPagesOfEachLocale(): void
    {
        $builder = $this->builder();
        $builder->setLocales(['en', 'fr'], 'en');
        $builder->setLocaleAdditionalPaths([
            'en' => ['/guide'],
            'fr' => ['/fr-only'],
        ]);

        $result = $builder->build();

        self::assertSame(['/', '/guide'], $result->builtPagesByLocale['en']);
        self::assertSame(['/', '/fr-only'], $result->builtPagesByLocale['fr']);
        self::assertSame(['/', '/guide', '/fr-only'], $result->builtPages);
    }

    private function builder(): StaticSiteBuilder
    {
        $router = (new Router())->get('/', PageController::class . '@home')
            ->get('/{page}', PageController::class . '@page');
        $app = ApplicationBuilder::create()
            ->withRouter($router)
            ->withControllerFactory(fn (string $class): object => new $class())
            ->build();

        $builder = new StaticSiteBuilder($app, $router);
        $builder->setOutputDirectory($this->outputDir);

        return $builder;
    }
}
