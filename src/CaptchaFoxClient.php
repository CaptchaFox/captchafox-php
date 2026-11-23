<?php

declare(strict_types=1);

namespace CaptchaFox;

/**
 * Verifies tokens of the CaptchaFox widget with siteverify, with the secret key of a site. An instance is immutable;
 * create it once and reuse it.
 */
final class CaptchaFoxClient
{
    private const ENDPOINT = 'https://api.captchafox.com/siteverify';

    // curl takes the timeout as a C long, which has 32 bits on Windows.
    private const MAX_TIMEOUT_MS = 2147483647;

    // Wrapped, so that var_dump, print_r, var_export and serialize of the client never show it.
    private readonly \SensitiveParameterValue $secret;

    private readonly float $timeout;

    // Not readonly, so that the tests can point it to a local siteverify.
    private string $endpoint = self::ENDPOINT;

    /**
     * @param string $secret  the secret key of the site
     * @param float  $timeout how many seconds a verification may take, from connecting to siteverify to the end of
     *                        its answer
     *
     * @throws \InvalidArgumentException if the secret key is empty or the timeout is no positive number
     */
    public function __construct(#[\SensitiveParameter] string $secret, float $timeout = 5.0)
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('the secret key is empty');
        }

        if (!is_finite($timeout) || $timeout <= 0) {
            throw new \InvalidArgumentException('the timeout is no positive number');
        }

        $this->secret = new \SensitiveParameterValue($secret);
        $this->timeout = $timeout;
    }

    /**
     * Verifies a token, optionally with the site key it was issued for and the IP address of the visitor.
     *
     * @param ?string $token    the token the widget handed to the visitor's form
     * @param ?string $sitekey  the site key the token was issued for; not sent when null or empty
     * @param ?string $remoteIp the IP address of the visitor; not sent when null or empty
     *
     * @return Verification {@see Passed} or {@see Rejected}; an empty or null token is rejected with
     *                      missing-input-response without a request
     *
     * @throws VerificationUnavailableException if siteverify cannot be evaluated
     */
    public function verify(?string $token, ?string $sitekey = null, ?string $remoteIp = null): Verification
    {
        if ($token === null || $token === '') {
            return new Rejected('missing-input-response');
        }

        $fields = ['secret' => $this->secret->getValue(), 'response' => $token];
        if ($sitekey !== null && $sitekey !== '') {
            $fields['sitekey'] = $sitekey;
        }
        if ($remoteIp !== null && $remoteIp !== '') {
            $fields['remoteIp'] = $remoteIp;
        }

        $handle = curl_init($this->endpoint);
        if ($handle === false) {
            throw new VerificationUnavailableException('siteverify could not be reached: curl did not start');
        }

        $configured = curl_setopt_array($handle, [
            CURLOPT_POST => true,
            // The separator is given, because http_build_query takes arg_separator.output of php.ini otherwise.
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&'),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            // curl's defaults, set here so that checking the certificate and refusing redirects rest on no default.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            // Only the protocol of the endpoint: HTTPS, and HTTP for the local siteverify of the tests.
            CURLOPT_PROTOCOLS => str_starts_with($this->endpoint, 'https://') ? CURLPROTO_HTTPS : CURLPROTO_HTTP,
            // One deadline over connect, headers and body.
            CURLOPT_TIMEOUT_MS => (int) min(ceil($this->timeout * 1000), self::MAX_TIMEOUT_MS),
        ]);
        if (!$configured) {
            throw new VerificationUnavailableException('siteverify could not be reached: curl refused an option');
        }

        $body = curl_exec($handle);
        if ($body === false) {
            if (curl_errno($handle) === CURLE_OPERATION_TIMEDOUT) {
                throw new VerificationUnavailableException(sprintf('siteverify did not answer within %g s', $this->timeout));
            }
            throw new VerificationUnavailableException('siteverify could not be reached: ' . curl_error($handle));
        }

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($status !== 200) {
            throw new VerificationUnavailableException('siteverify answered with HTTP status ' . $status);
        }

        return self::evaluate($body);
    }

    private static function evaluate(string $body): Verification
    {
        try {
            $answer = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new VerificationUnavailableException('siteverify answered with a body that is no JSON', 0, $e);
        }

        if (!$answer instanceof \stdClass) {
            throw new VerificationUnavailableException('siteverify answered with JSON that is no object');
        }

        $success = $answer->success ?? null;
        if (!is_bool($success)) {
            throw new VerificationUnavailableException('siteverify answered without a boolean success');
        }

        if ($success) {
            return new Passed(self::stringOrNull($answer->challenge_ts ?? null), self::stringOrNull($answer->hostname ?? null));
        } else {
            return new Rejected(...self::strings($answer->{'error-codes'} ?? null));
        }
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * A rejection stays a rejection when its error codes are malformed; only the entries that are strings are kept.
     *
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
