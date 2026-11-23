<?php

declare(strict_types=1);

namespace CaptchaFox;

/**
 * siteverify answered "success": false, or the token was empty.
 */
final readonly class Rejected implements Verification
{
    /**
     * The error codes of siteverify, such as timeout-or-duplicate, possibly none.
     *
     * @var list<string>
     */
    public array $errorCodes;

    public function __construct(string ...$errorCodes)
    {
        $this->errorCodes = array_values($errorCodes);
    }

    public function isPassed(): bool
    {
        return false;
    }
}
