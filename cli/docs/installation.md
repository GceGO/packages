# Installation

```bash
composer require --dev gcego/cli
```

gcego/cli requires gcego/studio (and through it the eighteen foundation packages). Laravel's package discovery loads `Gcego\Cli\CliServiceProvider`, which:

- registers its services lazily; nothing is built until a command needs it;
- registers the `gcego:*` commands only when running in the console;
- registers itself with gcego/core as the package `gcego/cli`.

## First steps

```bash
php artisan gcego:install    # gcego/studio: config/gcego-studio.php, a starter panel and dashboard
php artisan gcego:doctor     # is everything wired?
php artisan gcego:about
```

## Publishing

```bash
php artisan vendor:publish --tag=gcego-cli-config   # config/gcego-cli.php
php artisan vendor:publish --tag=gcego-cli-stubs    # stubs/gcego/*.stub
```

## Optional packages

| Package | Enables |
| --- | --- |
| gcego/notifications | `gcego:make-notification` |
| gcego/workflows | `gcego:make-workflow` |
| gcego/settings | `gcego:make-settings` |
| gcego/analytics | `gcego:make-dataset`, `gcego:make-metric`, `gcego:make-report` |
| gcego/testing | `gcego:make-test`, `--test` on every generator |

A generator whose package is missing exits with 5 and prints the `composer require` command. The other commands are unaffected.
