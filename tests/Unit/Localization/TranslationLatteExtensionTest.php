<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Localization;

use Leaf\Localization\TranslationLatteExtension;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zephyrus\Localization\JsonLocaleLoader;
use Zephyrus\Localization\Translator;

final class TranslationLatteExtensionTest extends TestCase
{
    private string $localeDir;

    protected function setUp(): void
    {
        $this->localeDir = sys_get_temp_dir() . '/leaf-test-locales-' . uniqid();
        mkdir($this->localeDir . '/en', 0755, true);
        mkdir($this->localeDir . '/fr', 0755, true);

        file_put_contents($this->localeDir . '/en/strings.json', json_encode([
            'hero' => ['title' => 'Welcome', 'greeting' => 'Hello {name}'],
            'nav' => ['home' => 'Home'],
        ]));

        file_put_contents($this->localeDir . '/fr/strings.json', json_encode([
            'hero' => ['title' => 'Bienvenue', 'greeting' => 'Bonjour {name}'],
            'nav' => ['home' => 'Accueil'],
        ]));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->localeDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createExtension(string $defaultLocale = 'en', array $supportedLocales = ['en', 'fr']): TranslationLatteExtension
    {
        $loader = new JsonLocaleLoader($this->localeDir);
        $translator = new Translator($loader, $defaultLocale);
        return new TranslationLatteExtension($translator, $defaultLocale, $supportedLocales);
    }

    private function createEngine(TranslationLatteExtension $extension): Engine
    {
        $latte = new Engine();
        $latte->setLoader(new StringLoader());
        $latte->setTempDirectory(sys_get_temp_dir());
        $latte->addExtension($extension);
        return $latte;
    }

    #[Test]
    public function localizeFunctionTranslatesKeys(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $result = $latte->renderToString('{localize("hero.title")}');
        $this->assertSame('Welcome', $result);
    }

    #[Test]
    public function localizeSupportsParameterInterpolation(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $result = $latte->renderToString('{localize("hero.greeting", ["name" => "World"])}');
        $this->assertSame('Hello World', $result);
    }

    #[Test]
    public function i18nIsAliasForLocalize(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $result = $latte->renderToString('{i18n("hero.title")}');
        $this->assertSame('Welcome', $result);
    }

    #[Test]
    public function currentLocaleIsInjected(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $result = $latte->renderToString('{$currentLocale}');
        $this->assertSame('en', $result);
    }

    #[Test]
    public function supportedLocalesIsInjected(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $result = $latte->renderToString('{implode(",", $supportedLocales)}');
        $this->assertSame('en,fr', $result);
    }

    #[Test]
    public function setCurrentLocaleSwitchesTranslation(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $resultEn = $latte->renderToString('{localize("hero.title")}');
        $this->assertSame('Welcome', $resultEn);

        $ext->setCurrentLocale('fr');

        $resultFr = $latte->renderToString('{localize("hero.title")}');
        $this->assertSame('Bienvenue', $resultFr);
    }

    #[Test]
    public function getCurrentLocaleReturnsActiveLocale(): void
    {
        $ext = $this->createExtension();
        $this->assertSame('en', $ext->getCurrentLocale());

        $ext->setCurrentLocale('fr');
        $this->assertSame('fr', $ext->getCurrentLocale());
    }

    #[Test]
    public function templateSpecificVariablesTakePrecedence(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $result = $latte->renderToString('{$currentLocale}', ['currentLocale' => 'custom']);
        $this->assertSame('custom', $result);
    }

    #[Test]
    public function missingKeyReturnsKeyItself(): void
    {
        $ext = $this->createExtension();
        $latte = $this->createEngine($ext);

        $result = $latte->renderToString('{localize("nonexistent.key")}');
        $this->assertSame('nonexistent.key', $result);
    }
}
