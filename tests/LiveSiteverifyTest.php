<?php

declare(strict_types=1);

namespace CaptchaFox\Tests;

use CaptchaFox\CaptchaFoxClient;
use CaptchaFox\Passed;
use CaptchaFox\Rejected;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the public test keys against CaptchaFox's siteverify; needs the network.
 */
#[Group('live')]
final class LiveSiteverifyTest extends TestCase
{
    private const PASSING_SECRET = 'ok_11111111000000001111111100000000';
    private const FAILING_SECRET = 'ok_FFFFFFFF00000000FFFFFFFF00000000';
    private const SITEKEY = 'sk_11111111000000001111111100000000';
    private const TOKEN = '11111111000000001111111100000000';

    public function testTheTestKeyPasses(): void
    {
        self::assertInstanceOf(Passed::class, (new CaptchaFoxClient(self::PASSING_SECRET))->verify(self::TOKEN));
    }

    public function testTheTestKeyPassesWithSitekeyAndRemoteIp(): void
    {
        self::assertInstanceOf(Passed::class, (new CaptchaFoxClient(self::PASSING_SECRET))->verify(self::TOKEN, self::SITEKEY, '203.0.113.7'));
    }

    public function testTheFailingTestKeyIsRejected(): void
    {
        $verification = (new CaptchaFoxClient(self::FAILING_SECRET))->verify(self::TOKEN);

        self::assertInstanceOf(Rejected::class, $verification);
        self::assertSame(['timeout-or-duplicate'], $verification->errorCodes);
    }
}
