<?php

declare(strict_types=1);

namespace Gcego\Media\Uploads;

use Gcego\Media\Access\MediaAccess;
use Gcego\Media\MediaConfig;
use Gcego\Media\Support\Keys;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Livewire's temporary uploads, bound to the user (and tenant) who
 * uploaded them. Livewire signs its temporary references but does not tie
 * them to a person, so gcego/media records each finished upload's owner
 * (on Livewire's _finishUpload call, in the uploader's own request) and
 * accepts a temporary file only from that owner, within
 * gcego-media.uploads.temporary_expiration_minutes. Livewire removes
 * abandoned temporary files itself (after 24 hours).
 */
final readonly class TemporaryUploads
{
    public function __construct(private Cache $cache, private MediaConfig $config, private MediaAccess $access) {}

    /**
     * @param  list<string>  $filenames  Livewire temporary file names.
     */
    public function register(array $filenames): void
    {
        $user = $this->access->user();

        if (! $user instanceof Model) {
            return;
        }

        foreach ($filenames as $filename) {
            if (preg_match('/^[A-Za-z0-9._-]{1,255}$/', $filename) === 1) {
                $this->cache->put($this->key($filename), ['owner' => $this->owner($user), 'tenant' => $this->access->tenant()], now()->addMinutes($this->config->temporaryMinutes));
            }
        }
    }

    /**
     * Whether the current user may use this temporary file (uploaded by them, in this tenant, not expired).
     */
    public function owns(TemporaryUploadedFile $file): bool
    {
        $user = $this->access->user();
        $entry = $this->cache->get($this->key($file->getFilename()));

        return $user instanceof Model && is_array($entry) && hash_equals(Keys::string($entry['owner'] ?? ''), $this->owner($user)) && ($entry['tenant'] ?? null) === $this->access->tenant();
    }

    /**
     * A used (stored) temporary file cannot be used again.
     */
    public function consume(TemporaryUploadedFile $file): void
    {
        $this->cache->forget($this->key($file->getFilename()));
    }

    private function key(string $filename): string
    {
        return 'gcego-media:temporary:'.hash('sha256', $filename);
    }

    private function owner(Model $user): string
    {
        return hash('sha256', $user->getMorphClass().'|'.Keys::string($user->getKey()));
    }
}
