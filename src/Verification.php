<?php

declare(strict_types=1);

namespace CaptchaFox;

/**
 * The outcome of a token that siteverify evaluated: {@see Passed} or {@see Rejected}. A token whose verification
 * cannot be evaluated has no Verification; {@see CaptchaFoxClient::verify()} throws
 * {@see VerificationUnavailableException} instead.
 */
interface Verification
{
    /**
     * @return bool true for {@see Passed}, false for {@see Rejected}
     */
    public function isPassed(): bool;
}
