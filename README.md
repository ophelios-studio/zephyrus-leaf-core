# Zephyrus Leaf Core

Core library for building static sites with the [Zephyrus Framework](https://github.com/ophelios-studio/zephyrus). Provides the static site generator, content parsing, SEO tools, multi-locale support, and development server.

This is the **library** package. For a ready-to-use project template, see [zephyrus-framework/leaf](https://github.com/ophelios-studio/leaf).

## Quick Start

The fastest way to start a new Leaf project is with the template:

```bash
composer create-project zephyrus-framework/leaf my-site
cd my-site
composer dev
```

This gives you a working static site with docs, live-reload, and one-command builds out of the box.

## Installation

If you're integrating Leaf Core into an existing Zephyrus project:

```bash
composer require zephyrus-framework/leaf-core
```

## Architecture

Leaf follows the same pattern as Zephyrus itself:

| Package | Type | Purpose |
|---------|------|---------|
| `zephyrus-framework/leaf-core` | Library | Reusable classes, updated via `composer update` |
| `zephyrus-framework/leaf` | Project template | Scaffold for new projects, copied on `create-project` |

When features are added to the core, all projects using it get them on `composer update` without manual porting.

## What's Included

### Build Pipeline

- **`Leaf\BuildCommand`** - Standard build orchestrator. Handles page rendering, sitemap/robots generation, search index, and 404 handling. Supports post-build hooks for project-specific steps.
- **`Leaf\StaticSiteBuilder`** - Renders all routes through the Zephyrus application stack and writes static HTML. Supports multi-locale builds with the default locale at root.
- **`Leaf\StaticBuildResult`** - Immutable value object with build stats, errors, and the list of built pages.

### Application Bootstrap

- **`Leaf\Kernel`** - Abstract application bootstrap. Handles config loading, Latte engine setup, extension registration, and controller discovery. Projects extend this and override `createController()` for dependency injection.

### Content

- **`Leaf\Content\ContentLoader`** - Loads and indexes Markdown content from section/slug directory structure. Provides sidebar navigation, page ordering, and prev/next links.
- **`Leaf\Content\MarkdownParser`** - Markdown to HTML via CommonMark with GitHub Flavored Markdown, heading permalinks, front matter, and table of contents extraction.
- **`Leaf\Content\ParsedMarkdown`** - Immutable parsed document with HTML, front matter, and TOC.
- **`Leaf\Content\SearchIndexBuilder`** - Generates a JSON search index from all content files.

### SEO

- **`Leaf\Seo\SitemapGenerator`** - Generates `sitemap.xml` with multi-locale `xhtml:link` hreflang alternates. Default locale URLs are at root (no prefix).
- **`Leaf\Seo\RobotsGenerator`** - Generates `robots.txt` with optional sitemap reference.

### Localization

- **`Leaf\Localization\TranslationLatteExtension`** - Latte extension providing `localize()` / `i18n()` template functions and `$currentLocale`, `$defaultLocale`, `$supportedLocales` template variables.

### Development

- **`Leaf\DevRouter`** - PHP built-in server router with static file serving, locale prefix handling, and live-reload endpoint.
- **`Leaf\FileWatcher`** - Detects file changes for the live-reload system.

### Configuration

- **`Leaf\Config\LeafConfig`** - Typed configuration from the `leaf:` section in `config.yml`.
- **`Leaf\LeafLatteExtension`** - Injects Leaf config values as template globals (`$leafName`, `$leafBaseUrl`, `$leafProductionUrl`, etc.).

## Usage

### Extending the Kernel

Every Leaf project needs an `Application` class that extends `Leaf\Kernel`:

```php
use Leaf\Kernel;

final class Application extends Kernel
{
    protected function createController(string $class): object
    {
        // Inject dependencies into specific controllers
        if ($class === DocsController::class) {
            return new DocsController(
                $this->contentLoader,
                $this->searchIndexBuilder,
                $this->leafConfig,
            );
        }
        return new $class();
    }
}
```

The Kernel provides these protected properties for use in `createController()`:

- `$this->contentLoader` - Content loading and navigation
- `$this->searchIndexBuilder` - Search index generation
- `$this->leafConfig` - Leaf configuration
- `$this->markdownParser` - Markdown parser
- `$this->renderEngine` - Latte template engine
- `$this->translator` - Translation service (if localization is configured)
- `$this->translationExtension` - Translation Latte extension

### Build Script

A minimal `bin/build.php`:

```php
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/vendor/autoload.php';

use App\Models\Core\Application;
use Leaf\BuildCommand;

$app = new Application();
$command = new BuildCommand($app);
exit($command->run());
```

For project-specific build steps, use `onPostBuild()` and `addPaths()`:

```php
$command = new BuildCommand($app);

// Add paths for parameterized routes (e.g. blog posts)
$command->addPaths(['/blog', '/blog/my-post', '/blog/another-post']);

// Run custom steps after the standard pipeline
$command->onPostBuild(function ($result, $outputDir) {
    // Generate OG images, optimize assets, etc.
    passthru('node bin/generate-og-images.js');
});

exit($command->run());
```

### Dev Server

A minimal `bin/router.php`:

```php
define('DEV_SERVER', true);
require __DIR__ . '/../vendor/autoload.php';

if ((new \Leaf\DevRouter(__DIR__ . '/..'))->handle() === false) {
    return false;
}
```

### Multi-Locale Configuration

In `config.yml`:

```yaml
localization:
  locale: "en"
  supported_locales:
    - "en"
    - "fr"
  locale_path: "locale"

leaf:
  production_url: "https://example.com"
```

The default locale builds to the site root (`/`), other locales build to `/{locale}/`. The `production_url` enables automatic sitemap and robots.txt generation.

## Testing

```bash
composer test
```

## License

MIT
