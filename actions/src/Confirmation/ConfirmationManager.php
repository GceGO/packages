<?php

declare(strict_types=1);

namespace Gcego\Actions\Confirmation;

use Gcego\Actions\ActionsConfig;
use Gcego\Actions\Exceptions\ActionConfirmationException;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Security\Authorization\AuthorizationContext;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Signed, single-use confirmation tokens.
 *
 * A token is issued only after the action was authorized, and binds:
 * the action identity, the record key (or the selection), the user and
 * guard, the tenant, an expiry and a nonce. It is an HMAC (application key)
 * over those values, so a browser cannot forge, alter or transplant it; it
 * expires (confirmation.ttl); and its nonce can be consumed once (atomic
 * cache add), so a replayed or double-submitted confirmation is refused.
 * A plain "confirmed=true" is never accepted.
 */
final readonly class ConfirmationManager
{
    public function __construct(
        private Repository $cache,
        private ActionsConfig $config,
        private string $key,
    ) {}

    /**
     * @param  list<string|int>  $selection
     */
    public function issue(ResolvedAction $action, AuthorizationContext $context, string|int|null $record = null, array $selection = []): string
    {
        $payload = (string) json_encode([
            'a' => $action->identity(),
            'r' => $record === null ? null : (string) $record,
            's' => $selection === [] ? null : self::selectionHash($selection),
            'u' => $context->userKey() === null ? null : (string) $context->userKey(),
            'g' => $context->guard,
            't' => $context->tenant?->id(),
            'e' => Date::now()->getTimestamp() + $this->config->confirmationTtl,
            'n' => Str::random(32),
        ]);
        $encoded = self::encode($payload);

        return $encoded.'.'.self::encode(hash_hmac('sha256', $encoded, $this->key, true));
    }

    /**
     * Verify and consume. Throws for any mismatch, expiry or reuse.
     *
     * @param  list<string|int>  $selection
     *
     * @throws ActionConfirmationException
     */
    public function consume(string $token, ResolvedAction $action, AuthorizationContext $context, string|int|null $record = null, array $selection = []): void
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if ($encoded === '' || ! hash_equals(self::encode(hash_hmac('sha256', $encoded, $this->key, true)), $signature)) {
            throw ActionConfirmationException::because('the token is not authentic.');
        }

        $payload = json_decode(self::decode($encoded), true);

        if (! is_array($payload)) {
            throw ActionConfirmationException::because('the token is malformed.');
        }

        $expected = [
            'a' => $action->identity(),
            'r' => $record === null ? null : (string) $record,
            's' => $selection === [] ? null : self::selectionHash($selection),
            'u' => $context->userKey() === null ? null : (string) $context->userKey(),
            'g' => $context->guard,
            't' => $context->tenant?->id(),
        ];

        foreach ($expected as $field => $value) {
            if (($payload[$field] ?? null) !== $value) {
                throw ActionConfirmationException::because('it was issued for another action, record, user or tenant.');
            }
        }

        if (! is_int($payload['e'] ?? null) || $payload['e'] < Date::now()->getTimestamp()) {
            throw ActionConfirmationException::because('it has expired.');
        }

        if (! is_string($payload['n'] ?? null) || ! $this->cache->add('gcego-actions:confirmation:'.$payload['n'], true, $this->config->confirmationTtl)) {
            throw ActionConfirmationException::because('it was already used.');
        }
    }

    /**
     * @param  list<string|int>  $selection
     */
    public static function selectionHash(array $selection): string
    {
        $keys = array_map(strval(...), $selection);
        sort($keys, SORT_STRING);

        return hash('sha256', implode("\0", $keys));
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
