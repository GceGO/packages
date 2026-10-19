<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Livewire;

use Gcego\Data\Source;
use Gcego\Security\Fields\FieldAuthorizer;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tests\Fixtures\Models\Subject;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * How a future gcego/forms component is expected to use gcego/security:
 * the record key is locked, and every request re-resolves and re-authorizes
 * the record and its writable fields on the server.
 */
final class SubjectEditor extends Component
{
    #[Locked]
    public int $subject = 0;

    public string $name = '';

    public bool $isPublished = false;

    public function mount(int $subject): void
    {
        $this->subject = $subject;
        $this->name = $this->record('view')->name;
    }

    public function save(): void
    {
        $record = $this->record('update');
        $fields = app(SecurityManager::class)->fields(['name', new FieldRule('is_published', write: new Permission('subjects.publish'))], $record);
        $data = app(FieldAuthorizer::class)->writable(array_filter(['name' => $this->name, 'is_published' => $this->isPublished ?: null], fn (mixed $value): bool => $value !== null), $fields);

        $record->update($data);
    }

    public function render(): string
    {
        return Blade::render('<div>{{ $name }}</div>', ['name' => $this->name]);
    }

    private function record(string $ability): Subject
    {
        /** @var Subject */
        return app(SecurityManager::class)->record(new Source(Subject::class), $this->subject, $ability);
    }
}
