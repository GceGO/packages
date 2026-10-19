<?php

declare(strict_types=1);

namespace Gcego\Testing\Factories;

use Gcego\Testing\Contracts\UserFixtureProvider;
use Gcego\Testing\Exceptions\InvalidFixtureException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Creates users with the application's own model.
 *
 * In order: the configured UserFixtureProvider; the auth provider's model
 * through its factory (HasFactory); otherwise the model itself with
 * synthetic name, email and password values for the columns it has.
 * Values are deterministic: the n-th user is "Test User n".
 */
final class TestUserFactory
{
    private int $sequence = 0;

    public function __construct(private readonly Container $container, private readonly ?UserFixtureProvider $provider = null) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): Authenticatable
    {
        if ($this->provider !== null) {
            return $this->provider->create($attributes);
        }

        $model = $this->model();

        if (method_exists($model, 'factory')) {
            $user = $model::factory()->create($attributes);

            if ($user instanceof Authenticatable) {
                return $user;
            }
        }

        $user = new $model;
        $sequence = ++$this->sequence;
        $defaults = ['name' => 'Test User '.$sequence, 'email' => 'user'.$sequence.'@example.test', 'password' => password_hash('password', PASSWORD_BCRYPT, ['cost' => 4])];
        $table = $user->getTable();

        foreach ($defaults as $column => $value) {
            if (! array_key_exists($column, $attributes) && Schema::connection($user->getConnectionName())->hasColumn($table, $column)) {
                $attributes[$column] = $value;
            }
        }

        $user->forceFill($attributes)->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<Authenticatable>
     */
    public function many(int $count, array $attributes = []): array
    {
        $users = [];

        for ($index = 0; $index < $count; $index++) {
            $users[] = $this->create($attributes);
        }

        return $users;
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    private function model(): string
    {
        $model = $this->container->make('config')->get('auth.providers.users.model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! is_subclass_of($model, Authenticatable::class)) {
            throw InvalidFixtureException::invalid('users', 'needs auth.providers.users.model to be an Eloquent Authenticatable model, or a UserFixtureProvider in gcego-testing.fixtures.users');
        }

        return $model;
    }
}
