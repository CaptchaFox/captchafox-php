# Development

## Requirements

PHP 8.3 or later with the extension `curl` and those PHPUnit requires, which the official PHP Docker images
have, and Composer 2. `composer install` installs PHPUnit at the versions of `composer.lock`.

## Test

| Command | Runs |
|---|---|
| `composer test` | The tests against a local siteverify server, including every case of `tests/__fixtures__/siteverify-cases.json`, and the check that the library requires nothing at runtime but PHP 8.3 and `ext-curl`. Needs no network once the dependencies are installed. CI runs it on PHP 8.3, 8.4, 8.5 and 8.6 for every push to `main` and every pull request. |
| `composer test-live` | The test against `https://api.captchafox.com/siteverify` with the public test keys. Needs the network. |

## Checks

| Command | Checks |
|---|---|
| `composer validate --strict` | `composer.json` and that `composer.lock` matches it. CI runs it with the tests. |
