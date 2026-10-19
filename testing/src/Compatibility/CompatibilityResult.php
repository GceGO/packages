<?php

declare(strict_types=1);

namespace Gcego\Testing\Compatibility;

final readonly class CompatibilityResult
{
    public const string Ok = 'ok';

    public const string Missing = 'missing';

    public const string Optional = 'optional';

    public const string Incompatible = 'incompatible';

    public function __construct(public string $name, public string $status, public ?string $version, public string $message) {}

    public function passes(): bool
    {
        return in_array($this->status, [self::Ok, self::Optional], true);
    }

    /**
     * @return array{name: string, status: string, version: ?string, message: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'status' => $this->status, 'version' => $this->version, 'message' => $this->message];
    }
}
