<?php

declare(strict_types=1);

namespace Gcego\Settings\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No registered setting, group or page has this name (404). Only
 * registered settings can be read or written: an unknown key is never
 * stored on the fly.
 */
final class SettingNotFoundException extends NotFoundHttpException implements SettingsException
{
    public static function setting(string $key): self
    {
        return new self(sprintf('No setting named "%s" is registered.', self::safe($key)));
    }

    public static function group(string $group): self
    {
        return new self(sprintf('No settings group named "%s" is registered.', self::safe($group)));
    }

    public static function page(string $page): self
    {
        return new self(sprintf('No settings page named "%s" is registered.', self::safe($page)));
    }

    private static function safe(string $name): string
    {
        return mb_substr((string) preg_replace('/[^A-Za-z0-9_.:-]/', '?', $name), 0, 64);
    }
}
