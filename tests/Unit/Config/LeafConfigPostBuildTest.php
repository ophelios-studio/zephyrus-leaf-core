<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Leaf\Config\LeafConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LeafConfigPostBuildTest extends TestCase
{
    #[Test]
    public function postBuildDefaultsToEmptyList(): void
    {
        $cfg = LeafConfig::fromArray(['name' => 'Site']);
        $this->assertSame([], $cfg->postBuild);
    }

    #[Test]
    public function postBuildAcceptsStringEntries(): void
    {
        $cfg = LeafConfig::fromArray([
            'name' => 'Site',
            'post_build' => [
                './scripts/one.sh',
                './scripts/two.sh',
            ],
        ]);
        $this->assertSame(
            [['./scripts/one.sh'], ['./scripts/two.sh']],
            $cfg->postBuild,
        );
    }

    #[Test]
    public function postBuildAcceptsArgvListEntries(): void
    {
        $cfg = LeafConfig::fromArray([
            'name' => 'Site',
            'post_build' => [
                ['./scripts/deploy.sh', '--prod'],
                'simple.sh',
            ],
        ]);
        $this->assertSame(
            [['./scripts/deploy.sh', '--prod'], ['simple.sh']],
            $cfg->postBuild,
        );
    }

    #[Test]
    public function postBuildSkipsEmptyAndInvalidEntries(): void
    {
        $cfg = LeafConfig::fromArray([
            'name' => 'Site',
            'post_build' => [
                '',                    // empty string dropped
                './ok.sh',             // kept
                [],                    // empty array dropped
                ['./with-args', ''],   // empty arg dropped, rest kept
            ],
        ]);
        $this->assertSame(
            [['./ok.sh'], ['./with-args']],
            $cfg->postBuild,
        );
    }

    #[Test]
    public function postBuildIgnoresNonArrayRoot(): void
    {
        $cfg = LeafConfig::fromArray([
            'name' => 'Site',
            'post_build' => 'not-an-array',
        ]);
        $this->assertSame([], $cfg->postBuild);
    }

    #[Test]
    public function acceptsCamelCaseKeyToo(): void
    {
        $cfg = LeafConfig::fromArray([
            'name' => 'Site',
            'postBuild' => ['./scripts/camel.sh'],
        ]);
        $this->assertSame([['./scripts/camel.sh']], $cfg->postBuild);
    }
}
