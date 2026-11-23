<?php

declare(strict_types=1);

namespace CaptchaFox\Tests;

use PHPUnit\Framework\TestCase;

final class RuntimeRequirementsTest extends TestCase
{
    public function testTheLibraryRequiresPhp83AndTheCurlExtensionOnly(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['php' => '^8.3', 'ext-curl' => '*'], $composer['require']);
    }
}
