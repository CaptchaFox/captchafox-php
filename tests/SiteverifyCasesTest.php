<?php

declare(strict_types=1);

namespace CaptchaFox\Tests;

use CaptchaFox\CaptchaFoxClient;
use CaptchaFox\Passed;
use CaptchaFox\Rejected;
use CaptchaFox\Tests\Support\LocalSiteverify;
use CaptchaFox\Tests\Support\SecretAssert;
use CaptchaFox\VerificationUnavailableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs every case of the siteverify specification against a local siteverify.
 */
final class SiteverifyCasesTest extends TestCase
{
    private const TIMEOUT = 5.0;

    // Short, so that the cases of a siteverify that stops answering end quickly.
    private const SILENCE_TIMEOUT = 1.0;

    // The local siteverify holds a connection for 10 s, so a case whose timeout does not take effect fails here.
    private const CASE_BOUND = 4.0;

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        foreach (self::specification()['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('cases')]
    public function testCase(array $case): void
    {
        $input = $case['input'];
        $answer = $case['answer'];
        $timeout = self::TIMEOUT;
        $server = null;

        if ($answer === null) {
            $server = LocalSiteverify::answering(500, '');
            $endpoint = $server->endpoint();
        } elseif (!array_key_exists('error', $answer)) {
            $server = LocalSiteverify::answering($answer['status'], $answer['body']);
            $endpoint = $server->endpoint();
        } else {
            switch ($answer['error']) {
                case 'timeout':
                    $server = LocalSiteverify::silent();
                    $endpoint = $server->endpoint();
                    $timeout = self::SILENCE_TIMEOUT;
                    break;
                case 'stall':
                    $server = LocalSiteverify::stalling($answer['status'], $answer['body']);
                    $endpoint = $server->endpoint();
                    $timeout = self::SILENCE_TIMEOUT;
                    break;
                case 'connection':
                    $endpoint = LocalSiteverify::refusingEndpoint();
                    break;
                default:
                    self::fail('unknown answer error ' . $answer['error']);
            }
        }

        try {
            $client = LocalSiteverify::point(new CaptchaFoxClient($input['secret'], $timeout), $endpoint);
            $start = hrtime(true);
            $outcome = self::outcome($client, $input['response'], $input['sitekey'] ?? null, $input['remoteIp'] ?? null);
            $elapsed = (hrtime(true) - $start) / 1e9;

            self::assertLessThan(self::CASE_BOUND, $elapsed, 'the verification took too long');
            self::expectOutcome($case['expect'], $input['secret'], $outcome);
            self::expectRequest($case['request'], $server);
        } finally {
            $server?->close();
        }
    }

    public function testTheEndpointIsTheOneOfTheSpecification(): void
    {
        $endpoint = (new \ReflectionProperty(CaptchaFoxClient::class, 'endpoint'))->getValue(new CaptchaFoxClient('ok_1'));

        self::assertSame(self::specification()['endpoint'], $endpoint);
    }

    private static function outcome(CaptchaFoxClient $client, string $token, ?string $sitekey, ?string $remoteIp): object
    {
        try {
            return $client->verify($token, $sitekey, $remoteIp);
        } catch (VerificationUnavailableException $e) {
            return $e;
        }
    }

    /**
     * @param array<string, mixed> $expect
     */
    private static function expectOutcome(array $expect, string $secret, object $outcome): void
    {
        switch ($expect['outcome']) {
            case 'passed':
                self::assertInstanceOf(Passed::class, $outcome);
                self::assertTrue($outcome->isPassed());
                self::assertSame($expect['challengeTs'] ?? null, $outcome->challengeTs);
                self::assertSame($expect['hostname'] ?? null, $outcome->hostname);
                break;
            case 'rejected':
                self::assertInstanceOf(Rejected::class, $outcome);
                self::assertFalse($outcome->isPassed());
                self::assertSame($expect['errorCodes'], $outcome->errorCodes);
                break;
            case 'unavailable':
                self::assertInstanceOf(VerificationUnavailableException::class, $outcome);
                SecretAssert::notIn($secret, $outcome);
                break;
            default:
                self::fail('unknown outcome ' . $expect['outcome']);
        }
    }

    /**
     * @param ?array<string, string> $request
     */
    private static function expectRequest(?array $request, ?LocalSiteverify $server): void
    {
        if ($request === null) {
            if ($server !== null) {
                self::assertSame([], $server->requests(), 'no request arrives');
            }
            return;
        }

        $received = $server->requests();
        self::assertCount(1, $received, 'one request arrives');
        self::assertSame('POST', $received[0]['method']);
        self::assertSame('/siteverify', $received[0]['target'], 'the URL carries nothing');
        self::assertSame('application/x-www-form-urlencoded', $received[0]['contentType']);
        self::assertSame($request, LocalSiteverify::form($received[0]['body']));
    }

    /**
     * @return array{endpoint: string, cases: list<array<string, mixed>>}
     */
    private static function specification(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/__fixtures__/siteverify-cases.json'), true, 512, JSON_THROW_ON_ERROR);
    }
}
