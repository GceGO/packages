<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Media\Definitions\MediaAbility;
use Gcego\Media\Exceptions\MediaNotFoundException;
use Gcego\Media\Exceptions\UploadException;
use Gcego\Media\MediaManager;
use Gcego\Media\Models\MediaRecord;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * gcego/media as the acting user: uploads validated by content (not by the
 * claimed extension), the media access policy, and tenant scoping of
 * lookups. Use Storage::fake() for the disks the collections write to.
 */
final class MediaTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    /**
     * Uploads through MediaManager and asserts it is stored.
     */
    public function upload(UploadedFile $file, ?string $collection = null): MediaRecord
    {
        $media = null;
        $exception = $this->caught(function () use ($file, $collection, &$media): void {
            $media = $this->manager()->upload($file, $collection);
        });

        $this->check($media instanceof MediaRecord, sprintf('upload(%s, %s)', $this->export($file->getClientOriginalName()), $this->export($collection)), 'Expected the upload to be accepted.', ['stored' => true], ['exception' => $this->describe($exception), 'reason' => $exception instanceof UploadException ? $exception->reason : null]);

        return $media instanceof MediaRecord ? $media : throw new \LogicException('Unreachable: the failed assertion stops the test.');
    }

    /**
     * The upload is refused; with $reason, for that reason ("type",
     * "mismatch", "dangerous", "too_large", "svg", "denied"…).
     */
    public function assertUploadRefused(UploadedFile $file, ?string $reason = null, ?string $collection = null): self
    {
        $exception = $this->caught(fn () => $this->manager()->upload($file, $collection));
        $actual = $exception instanceof UploadException ? $exception->reason : null;

        return $this->check($exception instanceof UploadException && ($reason === null || $actual === $reason), sprintf('assertUploadRefused(%s, %s)', $this->export($file->getClientOriginalName()), $this->export($reason)), 'Expected the upload to be refused.', ['reason' => $reason ?? '(any)'], ['reason' => $actual, 'exception' => $this->describe($exception)]);
    }

    public function assertCan(MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null): self
    {
        $allowed = $this->manager()->allows($ability, $media, $owner);

        return $this->check($allowed, sprintf('assertCan(%s)', $ability->value), sprintf('Expected the acting user to be allowed to %s the media.', $ability->value), ['allowed' => true], ['allowed' => false]);
    }

    public function assertCannot(MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null): self
    {
        $allowed = $this->manager()->allows($ability, $media, $owner);

        return $this->check(! $allowed, sprintf('assertCannot(%s)', $ability->value), sprintf('Expected the acting user to be refused %s on the media.', $ability->value), ['allowed' => false], ['allowed' => true]);
    }

    /**
     * Looking the media up by ULID finds nothing for the acting user
     * (another tenant's file, or a forged identifier).
     */
    public function assertNotFound(string|MediaRecord $media): self
    {
        $ulid = $this->ulid($media);
        $exception = $this->caught(fn () => $this->manager()->find($ulid));

        return $this->check($exception instanceof MediaNotFoundException, sprintf('assertNotFound(%s)', $this->export($ulid)), 'Expected the media not to be found.', ['exception' => MediaNotFoundException::class], ['exception' => $this->describe($exception)]);
    }

    public function assertFound(string|MediaRecord $media): self
    {
        $ulid = $this->ulid($media);
        $exception = $this->caught(fn () => $this->manager()->find($ulid));

        return $this->check($exception === null, sprintf('assertFound(%s)', $this->export($ulid)), 'Expected the media to be found.', ['found' => true], ['exception' => $this->describe($exception)]);
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect());

        return $this->check($problems === [], 'assertHealthy()', 'Expected the media inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'media';
    }

    protected function context(): array
    {
        $user = $this->environment->user();

        return ['User' => $user === null ? 'guest' : $user->getAuthIdentifier(), 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }

    private function ulid(string|MediaRecord $media): string
    {
        $ulid = $media instanceof MediaRecord ? $media->getAttribute('ulid') : $media;

        return is_string($ulid) ? $ulid : '';
    }

    private function manager(): MediaManager
    {
        return $this->container->make(MediaManager::class);
    }
}
