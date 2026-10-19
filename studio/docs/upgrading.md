# Upgrading

1. `composer update "gcego/*"`.
2. Run `php artisan gcego:studio:health`. `platform.compatibility` checks every installed GCEGO package's constraints against what is installed.
3. Compare your published `config/gcego-studio.php` with the package's, using the CHANGELOG. New keys fall back to defaults until you publish them.
4. Re-run `php artisan gcego:install --dry-run` to see whether the starter stubs changed. Your files are never replaced without `--force`.
5. Rebuild caches: `php artisan optimize:clear && php artisan optimize && php artisan gcego:registry-cache`.

Studio follows semantic versioning. In 0.x, minor versions may contain breaking changes, which are listed in the CHANGELOG.
