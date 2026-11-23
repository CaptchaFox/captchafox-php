<?php

declare(strict_types=1);

namespace CaptchaFox;

/**
 * siteverify answered "success": true.
 */
final readonly class Passed implements Verification
{
    /**
     * @param ?string $challengeTs the time of the challenge as an ISO 8601 timestamp, if siteverify sent one
     * @param ?string $hostname    the hostname of the site where the challenge was solved, if siteverify sent one
     */
    public function __construct(
        public ?string $challengeTs = null,
        public ?string $hostname = null,
    ) {
    }

    public function isPassed(): bool
    {
        return true;
    }
}
