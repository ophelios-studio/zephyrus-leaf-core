<?php

declare(strict_types=1);

namespace Leaf\Tests\Unit\Rendering;

use Leaf\Config\LeafConfig;
use Leaf\LeafLatteExtension;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LeafLatteExtensionTest extends TestCase
{
    private function createEngine(LeafLatteExtension $extension): Engine
    {
        $latte = new Engine();
        $latte->setLoader(new StringLoader());
        $latte->setTempDirectory(sys_get_temp_dir());
        $latte->addExtension($extension);
        return $latte;
    }

    #[Test]
    public function beforeRenderInjectsLeafVariables(): void
    {
        $config = LeafConfig::fromArray([
            'name' => 'Test Project',
            'version' => '2.0.0',
            'description' => 'Test description',
            'github_url' => 'https://github.com/test/repo',
            'author' => 'Test Author',
            'author_url' => 'https://example.com',
            'license' => 'MIT',
        ]);

        $extension = new LeafLatteExtension($config);
        $latte = $this->createEngine($extension);

        $result = $latte->renderToString('{$leafName} {$leafVersion}', []);

        $this->assertSame('Test Project 2.0.0', $result);
    }

    #[Test]
    public function templateSpecificVariablesTakePrecedence(): void
    {
        $config = LeafConfig::fromArray([
            'name' => 'Config Name',
        ]);

        $extension = new LeafLatteExtension($config);
        $latte = $this->createEngine($extension);

        // Pass leafName explicitly — it should override the extension's value.
        $result = $latte->renderToString('{$leafName}', ['leafName' => 'Override Name']);

        $this->assertSame('Override Name', $result);
    }

    #[Test]
    public function allLeafVariablesAreAvailable(): void
    {
        $config = LeafConfig::fromArray([
            'name' => 'AllVars',
            'version' => '1.0.0',
            'description' => 'desc',
            'github_url' => 'https://github.com/test',
            'author' => 'Author',
            'author_url' => 'https://author.com',
            'license' => 'MIT',
        ]);

        $extension = new LeafLatteExtension($config);
        $latte = $this->createEngine($extension);

        $template = '{$leafName}|{$leafVersion}|{$leafDescription}|{$leafGithubUrl}|{$leafAuthor}|{$leafAuthorUrl}|{$leafLicense}';
        $result = $latte->renderToString($template, []);

        $this->assertSame('AllVars|1.0.0|desc|https://github.com/test|Author|https://author.com|MIT', $result);
    }

    #[Test]
    public function worksWithDefaultConfig(): void
    {
        $config = LeafConfig::fromArray([]);

        $extension = new LeafLatteExtension($config);
        $latte = $this->createEngine($extension);

        $result = $latte->renderToString('{$leafName}', []);

        $this->assertSame('My Project', $result);
    }
}
