# CaptchaFox for PHP

Official [CaptchaFox](https://captchafox.com) integration for PHP. It verifies the answers of the widget with
CaptchaFox.

## Compatibility

| PHP | Library |
|---|---|
| 8.3, 8.4, 8.5, 8.6 | 1.x |

PHP 8.2 and older are not supported: Composer refuses to install the library there. The library needs the curl
extension (`ext-curl`).

## Installation

```bash
composer require captchafox/captchafox-php
```

Without Composer, download a [release](https://github.com/CaptchaFox/captchafox-php/releases) and have your PSR-4
autoloader load the namespace `CaptchaFox` from its `src` directory.

## Configuration

### Get your keys

In the [CaptchaFox portal](https://portal.captchafox.com), open your website under **Sites > Site Details** and copy
the **site key** and the **secret key**.

### Set up the library

Create a `CaptchaFoxClient` once with the secret key of your site and reuse it. Keep the secret key in your
configuration or environment, not in source code:

```php
use CaptchaFox\CaptchaFoxClient;

$client = new CaptchaFoxClient(getenv('CAPTCHAFOX_SECRET'));
```

### Options

| Option | Default | Meaning |
|---|---|---|
| `timeout` | 5 seconds | How many seconds a verification may take, from connecting to CaptchaFox to the end of its answer: `new CaptchaFoxClient($secret, timeout: 3.0)` |

## Usage

The widget adds the token to the form as the POST parameter `cf-captcha-response`. Verify it on the server before
the form is processed:

```php
use CaptchaFox\VerificationUnavailableException;

// A visitor can post an array under the name; only a string is a token.
$token = $_POST['cf-captcha-response'] ?? null;
$token = is_string($token) ? $token : null;
try {
    $verification = $client->verify($token, SITE_KEY, $_SERVER['REMOTE_ADDR']);
    if (!$verification->isPassed()) {
        // Reject the form.
    }
} catch (VerificationUnavailableException $e) {
    // CaptchaFox gave no usable answer: decide whether the form passes.
}
```

`verify($token)` sends the token alone; `verify($token, $sitekey, $remoteIp)` also sends the site key the token was
issued for and the visitor's IP address, each only when it is not `null` or empty.

A verification has one of three outcomes:

- `CaptchaFox\Passed`: CaptchaFox accepted the token. `$challengeTs` and `$hostname` hold the time of the challenge
  and the hostname of the site, where CaptchaFox sent them, and are `null` otherwise.
- `CaptchaFox\Rejected`: CaptchaFox rejected the token; `$errorCodes` holds its error codes, such as
  `timeout-or-duplicate`, possibly none. An empty or missing token is rejected with `missing-input-response` without
  asking CaptchaFox.
- `CaptchaFox\VerificationUnavailableException`: CaptchaFox gave no usable answer; see
  [If CaptchaFox is unreachable](#if-captchafox-is-unreachable).

## Operation

### If CaptchaFox is unreachable

When CaptchaFox cannot be reached, does not complete its answer within the timeout, answers with an HTTP status other
than 200, or with a body that is no valid answer, `verify` throws `VerificationUnavailableException`. Your code
decides whether the form passes then: letting it through keeps your forms available during an outage, rejecting it
keeps them protected. Tokens that CaptchaFox rejects are always `CaptchaFox\Rejected`.

### Log

The library logs nothing. The messages and stack traces of its exceptions never contain the secret key, and neither
do `var_dump`, `print_r` and `var_export` of a `CaptchaFoxClient`.

### Visitor IP

The visitor's IP address is sent only when you pass it as `remoteIp`. Behind a reverse proxy or load balancer, pass
the visitor's address that the proxy forwards, not the address of the proxy.

### Privacy

CaptchaFox is operated by Scoria Labs GmbH, Germany. How CaptchaFox processes data and how to get a data processing
agreement is described in the [CaptchaFox Privacy Center](https://captchafox.com/privacy). A suggested paragraph for
your privacy policy is on the [GDPR page](https://captchafox.com/privacy/gdpr).

## License

Apache-2.0. See [LICENSE](LICENSE).
