<?php

declare(strict_types=1);

namespace CaptchaFox\Tests\Support;

use CaptchaFox\CaptchaFoxClient;
use PHPUnit\Framework\Assert;

final class SecretAssert
{
    /**
     * Fails if the secret key is in the message of the throwable or of one of its causes, or in an argument of the
     * frames of their traces up to the public method of the client that was called. The frames above it belong to
     * the caller.
     */
    public static function notIn(string $secret, \Throwable $throwable): void
    {
        // Without arguments in the traces, the check of the frames would pass whatever they held.
        Assert::assertSame('0', ini_get('zend.exception_ignore_args'), 'traces carry no arguments');

        for ($cause = $throwable; $cause !== null; $cause = $cause->getPrevious()) {
            Assert::assertStringNotContainsString($secret, $cause->getMessage(), 'the secret key is in the message of ' . $cause::class);

            foreach ($cause->getTrace() as $frame) {
                $frameName = ($frame['class'] ?? '') . '::' . $frame['function'];
                Assert::assertStringNotContainsString($secret, print_r($frame['args'] ?? [], true), 'the secret key is in the arguments of ' . $frameName);

                if (($frame['class'] ?? null) === CaptchaFoxClient::class && in_array($frame['function'], ['__construct', 'verify'], true)) {
                    break;
                }
            }
        }
    }
}
