<?php

declare(strict_types=1);

namespace CaptchaFox\Tests\Integrator;

use CaptchaFox\CaptchaFoxClient;
use CaptchaFox\Passed;
use CaptchaFox\Rejected;
use CaptchaFox\Verification;
use CaptchaFox\VerificationUnavailableException;
use PHPUnit\Framework\TestCase;

/**
 * Uses the library as an integrator does, with the names of its parameters: a public member that goes or is renamed
 * fails here.
 */
final class PublicInterfaceTest extends TestCase
{
    private const SECRET = 'ok_11111111000000001111111100000000';
    private const SITEKEY = 'sk_11111111000000001111111100000000';

    // An empty token is rejected without a request, so no network is needed.
    public function testEveryPublicMemberIsReachable(): void
    {
        $client = new CaptchaFoxClient(self::SECRET);
        $withTimeout = new CaptchaFoxClient(secret: self::SECRET, timeout: 3.0);

        self::assertSame('rejected: missing-input-response', self::describe($client->verify('')));
        self::assertSame('rejected: missing-input-response', self::describe($client->verify(null)));
        self::assertSame('rejected: missing-input-response', self::describe($withTimeout->verify('', self::SITEKEY, '203.0.113.7')));
        self::assertSame('rejected: missing-input-response', self::describe($withTimeout->verify(token: '', sitekey: self::SITEKEY, remoteIp: '203.0.113.7')));

        self::assertSame('passed: 2026-10-20T10:00:00Z example.com', self::describe(new Passed('2026-10-20T10:00:00Z', 'example.com')));
        self::assertSame('passed:  ', self::describe(new Passed()));
        self::assertSame('passed: 2026-10-20T10:00:00Z example.com', self::describe(new Passed(challengeTs: '2026-10-20T10:00:00Z', hostname: 'example.com')));
        self::assertSame('rejected: timeout-or-duplicate,invalid-ip-match', self::describe(new Rejected('timeout-or-duplicate', 'invalid-ip-match')));
        self::assertSame('rejected: ', self::describe(new Rejected()));
    }

    public function testTheExceptionIsARuntimeException(): void
    {
        $exception = new VerificationUnavailableException('siteverify could not be reached');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    private static function describe(Verification $verification): string
    {
        if ($verification->isPassed()) {
            self::assertInstanceOf(Passed::class, $verification);
            return 'passed: ' . ($verification->challengeTs ?? '') . ' ' . ($verification->hostname ?? '');
        } else {
            self::assertInstanceOf(Rejected::class, $verification);
            return 'rejected: ' . implode(',', $verification->errorCodes);
        }
    }
}
