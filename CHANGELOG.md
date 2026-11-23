## 1.0.0

- Verify CaptchaFox tokens with siteverify: `CaptchaFoxClient::verify` returns `Passed` or `Rejected`, and throws
  `VerificationUnavailableException` when CaptchaFox cannot be reached in time or answers unexpectedly.
- PHP 8.3 and later, only `ext-curl` at runtime.
