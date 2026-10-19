<?php

declare(strict_types=1);

namespace Gcego\Vault\Tests\Fixtures\Policies;

use Gcego\Vault\Tests\Fixtures\Models\Book;
use Gcego\Vault\Tests\Fixtures\Models\User;

/**
 * A policy backed by Vault permissions plus record conditions: editors
 * update only their own books, unless they hold books.updateAny.
 */
final class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('books.viewAny');
    }

    public function view(User $user, Book $book): bool
    {
        return $user->hasPermissionTo('books.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('books.create');
    }

    public function update(User $user, Book $book): bool
    {
        return $user->hasPermissionTo('books.update') && ($book->owner_id === $user->id || $user->hasPermissionTo('books.updateAny'));
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->hasPermissionTo('books.delete');
    }
}
