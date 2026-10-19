<?php

declare(strict_types=1);

namespace Gcego\Luminaries\Providers;

use Gcego\Luminaries\Contracts\IconProvider;
use Gcego\Luminaries\Exceptions\IconNotFoundException;
use Gcego\Luminaries\Exceptions\UnsafeSvgException;
use Gcego\Luminaries\IconName;
use Gcego\Luminaries\Security\PathGuard;
use Gcego\Luminaries\Security\TrustedSvgValidator;
use Gcego\Luminaries\Support\ProviderMetadata;

/**
 * A directory of application SVG files addressed as "{collection}:{file name}".
 *
 * Files are not trusted because of where they live: the directory must be
 * inside a trusted root (PathGuard) and every file is validated by
 * TrustedSvgValidator before it is rendered. The exact bytes that were
 * validated are the bytes rendered (they are not re-read through Blade
 * Icons), so a file cannot change between validation and output. Validation
 * results are memoized per file path, modification time and size.
 */
final class CustomSvgProvider implements IconProvider
{
    private const int MemoLimit = 2048;

    private ?string $directory = null;

    private ?string $problem = null;

    /**
     * @var array<string, array{key: string, contents: ?string, violations: list<string>}>
     */
    private array $files = [];

    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly string $path,
        private readonly PathGuard $guard,
        private readonly TrustedSvgValidator $validator,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function has(string $icon): bool
    {
        return $this->load($icon)['contents'] !== null;
    }

    public function contents(string $icon): string
    {
        $file = $this->load($icon);

        if ($file['contents'] !== null) {
            return $file['contents'];
        }

        if ($file['violations'] !== []) {
            throw UnsafeSvgException::file(IconName::canonical($this->name, $icon), $this->directory().'/'.$icon.'.svg', $file['violations']);
        }

        throw new IconNotFoundException(IconName::canonical($this->name, $icon), $this->name, null, $this->problem() ?? sprintf('There is no file "%s.svg" in %s.', $icon, $this->path));
    }

    /**
     * Policy violations of an existing file (empty when the file is missing or acceptable).
     *
     * @return list<string>
     */
    public function violations(string $icon): array
    {
        return $this->load($icon)['violations'];
    }

    /**
     * Why the collection directory cannot be used, or null when it is usable.
     */
    public function problem(): ?string
    {
        $this->directory();

        return $this->problem;
    }

    public function nativeName(string $icon): ?string
    {
        return null;
    }

    public function icons(): array
    {
        $directory = $this->directory();

        if ($directory === null) {
            return [];
        }

        $icons = [];

        foreach (glob($directory.'/*.svg') ?: [] as $file) {
            $icon = basename($file, '.svg');

            if (preg_match(IconName::IconPattern, $icon) === 1) {
                $icons[] = $icon;
            }
        }

        sort($icons);

        return $icons;
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata($this->name, $this->label, 'custom', 'custom', false, 'custom SVG collection', $this->path);
    }

    private function directory(): ?string
    {
        if ($this->directory !== null || $this->problem !== null) {
            return $this->directory;
        }

        try {
            return $this->directory = $this->guard->directory($this->name, $this->path);
        } catch (UnsafeSvgException $exception) {
            $this->problem = trim((string) preg_replace('/^GCEGO Luminaries\s*/', '', $exception->getMessage()));

            return null;
        }
    }

    /**
     * @return array{key: string, contents: ?string, violations: list<string>}
     */
    private function load(string $icon): array
    {
        $directory = $this->directory();
        $real = $directory === null ? null : $this->guard->file($directory, $icon);

        if ($real === null) {
            return ['key' => '', 'contents' => null, 'violations' => []];
        }

        $key = $real.'|'.(int) filemtime($real).'|'.(int) filesize($real);

        if (isset($this->files[$icon]) && $this->files[$icon]['key'] === $key) {
            return $this->files[$icon];
        }

        if (count($this->files) >= self::MemoLimit) {
            $this->files = [];
        }

        $size = (int) filesize($real);
        $contents = $size > TrustedSvgValidator::MaxBytes ? null : file_get_contents($real);

        if (! is_string($contents)) {
            return $this->files[$icon] = ['key' => $key, 'contents' => null, 'violations' => [sprintf('the file is unreadable or larger than %d bytes', TrustedSvgValidator::MaxBytes)]];
        }

        $violations = $this->validator->validate($contents);
        $clean = trim((string) preg_replace('/^\s*<\?xml[^>]*\?>/i', '', $contents));

        return $this->files[$icon] = ['key' => $key, 'contents' => $violations === [] ? $clean : null, 'violations' => $violations];
    }
}
