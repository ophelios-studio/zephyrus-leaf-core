<?php

declare(strict_types=1);

namespace Leaf\Localization;

use Latte\Extension;
use Latte\Runtime\Template;
use Zephyrus\Localization\Translator;

/**
 * Latte extension that provides translation support in templates.
 *
 * Registers the following Latte functions:
 *
 *   {localize('key')}              — Translate a key using the current locale
 *   {localize('key', [...])}       — Translate with parameter interpolation
 *   {i18n('key')}                  — Alias for localize()
 *
 * Also injects template variables:
 *
 *   {$currentLocale}               — The active locale code (e.g. "en", "fr")
 *   {$supportedLocales}            — Array of all supported locale codes
 *
 * The current locale can be switched at runtime via setCurrentLocale(),
 * which is used by the StaticSiteBuilder to generate pages for each locale.
 */
final class TranslationLatteExtension extends Extension
{
    private string $currentLocale;

    /**
     * @param list<string> $supportedLocales
     */
    public function __construct(
        private readonly Translator $translator,
        private readonly string $defaultLocale,
        private readonly array $supportedLocales,
    ) {
        $this->currentLocale = $defaultLocale;
    }

    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    public function setCurrentLocale(string $locale): void
    {
        $this->currentLocale = $locale;
    }

    /**
     * Register localize() and i18n() as native Latte functions.
     *
     * Usage in templates:
     *   {localize('hero.title')}
     *   {localize('greeting', ['name' => 'World'])}
     *   {i18n('hero.title')}
     *
     * @return array<string, callable>
     */
    public function getFunctions(): array
    {
        // Closures capture $this so they always read the current locale.
        $fn = fn (string $key, array $parameters = []): string
            => $this->translator->trans($key, $parameters, $this->currentLocale);

        return [
            'localize' => $fn,
            'i18n' => $fn,
        ];
    }

    public function beforeRender(Template $template): void
    {
        $locale = $this->currentLocale;
        $supportedLocales = $this->supportedLocales;

        $existing = $template->getParameters();

        $defaultLocale = $this->defaultLocale;

        $setter = \Closure::bind(function () use ($locale, $defaultLocale, $supportedLocales, $existing): void {
            if (!array_key_exists('currentLocale', $existing)) {
                $this->params['currentLocale'] = $locale;
            }
            if (!array_key_exists('defaultLocale', $existing)) {
                $this->params['defaultLocale'] = $defaultLocale;
            }
            if (!array_key_exists('supportedLocales', $existing)) {
                $this->params['supportedLocales'] = $supportedLocales;
            }
        }, $template, Template::class);

        $setter();
    }
}
