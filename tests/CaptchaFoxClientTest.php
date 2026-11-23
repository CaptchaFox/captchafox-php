<?php

declare(strict_types=1);

namespace CaptchaFox\Tests;

use CaptchaFox\CaptchaFoxClient;
use CaptchaFox\Rejected;
use CaptchaFox\Tests\Support\LocalSiteverify;
use CaptchaFox\Tests\Support\SecretAssert;
use CaptchaFox\VerificationUnavailableException;
use PHPUnit\Framework\TestCase;

/**
 * What the specification's cases leave out.
 */
final class CaptchaFoxClientTest extends TestCase
{
    private const SECRET = 'ok_11111111000000001111111100000000';

    public function testFieldsArriveUnchangedWhateverTheirCharacters(): void
    {
        $server = LocalSiteverify::answering(200, '{"success":true}');
        $separator = ini_set('arg_separator.output', '&amp;');
        try {
            $secret = 'ok_a+b&c=d %ä';
            $token = 't+&=/?#% ä€';
            LocalSiteverify::point(new CaptchaFoxClient($secret), $server->endpoint())->verify($token, 'sk_1', '2001:db8::1');

            $expected = ['secret' => $secret, 'response' => $token, 'sitekey' => 'sk_1', 'remoteIp' => '2001:db8::1'];
            self::assertSame($expected, LocalSiteverify::form($server->requests()[0]['body']));
        } finally {
            ini_set('arg_separator.output', $separator);
            $server->close();
        }
    }

    public function testANullTokenIsRejectedWithoutARequest(): void
    {
        $server = LocalSiteverify::answering(200, '{"success":true}');
        try {
            $verification = LocalSiteverify::point(new CaptchaFoxClient(self::SECRET), $server->endpoint())->verify(null);

            self::assertInstanceOf(Rejected::class, $verification);
            self::assertSame(['missing-input-response'], $verification->errorCodes);
            self::assertSame([], $server->requests());
        } finally {
            $server->close();
        }
    }

    public function testADeeplyNestedBodyCannotBeEvaluated(): void
    {
        $server = LocalSiteverify::answering(200, '{"success":true,"x":' . str_repeat('[', 100_000) . str_repeat(']', 100_000) . '}');
        try {
            $this->expectException(VerificationUnavailableException::class);

            LocalSiteverify::point(new CaptchaFoxClient(self::SECRET), $server->endpoint())->verify('token');
        } finally {
            $server->close();
        }
    }

    public function testARedirectIsNotFollowedAndCannotBeEvaluated(): void
    {
        $target = LocalSiteverify::answering(200, '{"success":true}');
        $server = LocalSiteverify::redirecting(302, $target->endpoint());
        try {
            $client = LocalSiteverify::point(new CaptchaFoxClient(self::SECRET), $server->endpoint());
            try {
                $client->verify('token');
                self::fail('the verification was evaluated');
            } catch (VerificationUnavailableException) {
            }

            self::assertCount(1, $server->requests());
            self::assertSame([], $target->requests(), 'the redirect was followed');
        } finally {
            $server->close();
            $target->close();
        }
    }

    public function testTheDefaultTimeoutIsFiveSeconds(): void
    {
        $timeout = (new \ReflectionProperty(CaptchaFoxClient::class, 'timeout'))->getValue(new CaptchaFoxClient(self::SECRET));

        self::assertSame(5.0, $timeout);
    }

    public function testTheTimeoutEndsAnAnswerThatNeverCompletesAndClosesTheConnection(): void
    {
        $server = LocalSiteverify::trickling(200, '{"success":true');
        try {
            $client = LocalSiteverify::point(new CaptchaFoxClient(self::SECRET, 1.0), $server->endpoint());
            $start = hrtime(true);
            try {
                $client->verify('token');
                self::fail('the verification passed');
            } catch (VerificationUnavailableException) {
            }
            $elapsed = (hrtime(true) - $start) / 1e9;
            self::assertLessThan(3.0, $elapsed, "the verification took $elapsed s");

            // The server notices the closed connection when it writes its next space.
            $limit = hrtime(true) + 5_000_000_000;
            while (!$server->connectionBroken() && hrtime(true) < $limit) {
                usleep(50_000);
            }
            self::assertTrue($server->connectionBroken(), 'the connection is still open');
        } finally {
            $server->close();
        }
    }

    public function testTheSecretKeyIsNotInADumpOfTheClient(): void
    {
        $client = new CaptchaFoxClient(self::SECRET);

        ob_start();
        var_dump($client);
        $dump = ob_get_clean();

        self::assertStringNotContainsString(self::SECRET, $dump);
        self::assertStringNotContainsString(self::SECRET, print_r($client, true));
        self::assertStringNotContainsString(self::SECRET, var_export($client, true));
        self::assertStringNotContainsString(self::SECRET, print_r((array) $client, true));
    }

    public function testTheClientCannotBeSerialized(): void
    {
        $this->expectException(\Exception::class);

        serialize(new CaptchaFoxClient(self::SECRET));
    }

    public function testTheSecretKeyIsNotInTheTraceOfARefusedTimeout(): void
    {
        try {
            new CaptchaFoxClient(self::SECRET, 0.0);
            self::fail('the timeout was accepted');
        } catch (\InvalidArgumentException $e) {
            SecretAssert::notIn(self::SECRET, $e);
        }
    }

    public function testAnEmptySecretKeyOrNoPositiveTimeoutIsRefused(): void
    {
        foreach ([['', 5.0], [self::SECRET, 0.0], [self::SECRET, -1.0], [self::SECRET, NAN], [self::SECRET, INF]] as [$secret, $timeout]) {
            try {
                new CaptchaFoxClient($secret, $timeout);
                self::fail("the client was created with the timeout $timeout");
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
