<?php

declare(strict_types=1);

use Gcego\Actions\Livewire\ActionHost;
use Gcego\Forms\Livewire\FormHost;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Vault\Tests\Fixtures\Models\Book;
use Livewire\Livewire;

/**
 * Vault behind the real engines: panel access, module access, resource pages,
 * table columns, bulk actions, form fields and navigation, all decided on the server.
 */
beforeEach(function (): void {
    synced();
    role('reader', ['panels.desk.access', 'modules.library.access', 'books.viewAny', 'books.view']);
    role('librarian', ['books.create', 'books.update', 'books.updateAny', 'books.delete', 'books.isbn.view', 'books.isbn.update'], inherits: ['reader']);
});

it('refuses the panel without its access permission, whatever else the person holds', function (): void {
    role('books-only', ['books.viewAny', 'books.view', 'modules.library.access']);
    $user = person('No Panel');
    $user->assignRole('books-only');

    $this->actingAs($user)->get('/desk/books')->assertForbidden();
});

it('refuses the module\'s pages without the module permission', function (): void {
    role('panel-only', ['panels.desk.access', 'books.viewAny', 'books.view']);
    $user = person('No Module');
    $user->assignRole('panel-only');

    $this->actingAs($user)->get('/desk/books')->assertForbidden();
});

it('opens resource pages with panel, module and resource permissions, and refuses unheld abilities', function (): void {
    $reader = person('Reader');
    $reader->assignRole('reader');

    $this->actingAs($reader)->get('/desk/books')->assertOk();
    $this->actingAs($reader)->get('/desk/books/create')->assertForbidden();
});

it('withdraws access on the next request after a revocation', function (): void {
    $reader = person('Reader');
    $reader->assignRole('reader');
    $this->actingAs($reader)->get('/desk/books')->assertOk();

    $reader->removeRole('reader');
    app()->forgetScopedInstances();

    $this->actingAs($reader)->get('/desk/books')->assertForbidden();
});

it('removes a column whose read permission is not held', function (): void {
    Book::query()->create(['title' => 'Dune', 'isbn' => '978-0441013593']);
    $this->actingAs($reader = person('Reader'));
    $reader->assignRole('reader');

    expect(Livewire::test(TableHost::class, ['definition' => 'page:library-books'])->html())->toContain('Dune')->not->toContain('978-0441013593');

    $librarian = person('Librarian');
    $librarian->assignRole('librarian');
    $this->actingAs($librarian);
    app()->forgetScopedInstances();

    expect(Livewire::test(TableHost::class, ['definition' => 'page:library-books'])->html())->toContain('978-0441013593');
});

it('offers bulk delete only to holders of the delete permission, and refuses it on the server', function (): void {
    $book = Book::query()->create(['title' => 'Dune']);
    $reader = person('Reader');
    $reader->assignRole('reader');
    $this->actingAs($reader);

    expect(Livewire::test(TableHost::class, ['definition' => 'page:library-books'])->set('selected', [(string) $book->id])->html())->not->toContain("runBulkAction('bulk-delete')");

    Livewire::test(ActionHost::class, ['definition' => 'page:library-books', 'placement' => 'bulk'])
        ->call('runBulk', 'bulk-delete', [$book->id])
        ->assertSet('feedback.status', 'denied');

    expect(Book::query()->count())->toBe(1);

    $librarian = person('Librarian');
    $librarian->assignRole('librarian');
    $this->actingAs($librarian);
    app()->forgetScopedInstances();

    Livewire::test(ActionHost::class, ['definition' => 'page:library-books', 'placement' => 'bulk'])
        ->call('runBulk', 'bulk-delete', [$book->id])
        ->assertSet('dialog.action', 'bulk-delete')
        ->call('submit');

    expect(Book::query()->count())->toBe(0);
});

it('ignores a field the person may not write, even when the request contains it', function (): void {
    $book = Book::query()->create(['title' => 'Dune', 'isbn' => 'original', 'owner_id' => null]);
    $editor = person('Editor');
    $editor->assignRole(role('editor', ['books.update', 'books.updateAny', 'books.isbn.view'], inherits: ['reader']));
    $this->actingAs($editor);

    Livewire::test(FormHost::class, ['definition' => 'page:library-books-edit', 'record' => (string) $book->id])
        ->set('data.title', 'Dune (revised)')
        ->set('data.isbn', 'tampered')
        ->call('submit');

    expect($book->refresh()->title)->toBe('Dune (revised)')->and($book->isbn)->toBe('original');
});

it('applies record conditions of policies on top of permissions', function (): void {
    $theirs = Book::query()->create(['title' => 'Theirs', 'owner_id' => 999]);
    $editor = person('Editor');
    $editor->assignRole(role('own-editor', ['books.update'], inherits: ['reader']));

    $own = Book::query()->create(['title' => 'Mine', 'owner_id' => $editor->id]);

    $this->actingAs($editor)->get('/desk/books/'.$theirs->id.'/edit')->assertForbidden();
    $this->actingAs($editor)->get('/desk/books/'.$own->id.'/edit')->assertOk();
});
