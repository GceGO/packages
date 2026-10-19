# CI integration

A workflow that verifies a GCEGO application. Each step fails the job through its exit code.

```yaml
- run: composer validate --strict
- run: composer install --no-interaction --prefer-dist
- run: find app -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null
- run: vendor/bin/phpstan analyse
- run: php artisan gcego:packages --no-interaction                       # dependency compatibility (exit 5)
- run: php artisan gcego:validate --no-interaction --format=json > validation.json   # exit 3
- run: php artisan test --compact
- run: php artisan gcego:doctor --no-interaction --format=json > doctor.json         # exit 6
- run: npm ci && npm run build && php artisan gcego:assets --no-interaction          # exit 6
```

For a production-readiness gate before deployment:

```bash
php artisan optimize
php artisan gcego:registry-cache
php artisan gcego:doctor --production --no-interaction
```

Keep the JSON files as build artifacts: they have a stable schema (`"schema": 1`).

Results are what the commands report on your application; nothing is assumed to pass.
