<?php

declare(strict_types=1);

namespace Gcego\Security\Fields;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Exceptions\InvalidPermissionException;
use Gcego\Security\SecurityConfig;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Server-side field access. Future forms, tables and infolists call it
 * after authorizing the record itself:
 *
 *     $fields = $security->fields(['name', 'email', new FieldRule('national_id', read: ..., write: ...)], $student);
 *     $data = $authorizer->writable($request->all(), $fields);   // rejects undeclared and read-only keys
 *     $output = $authorizer->visible($student, $fields);         // hides or masks unreadable fields
 *
 * A field hidden in the UI is therefore never writable through a crafted
 * request, and an unreadable value never leaves the server.
 */
final readonly class FieldAuthorizer
{
    public function __construct(
        private AuthorizationEngine $engine,
        private SecurityConfig $config,
    ) {}

    /**
     * @param  list<string|FieldRule>  $fields  Declared fields; plain names have no extra rule.
     */
    public function decide(array $fields, mixed $record = null, ?AuthorizationContext $context = null): FieldDecisions
    {
        $context ??= $this->engine->context();
        $decisions = [];

        foreach ($fields as $field) {
            $rule = $field instanceof FieldRule ? $field : new FieldRule($field);

            if (isset($decisions[$rule->field])) {
                throw InvalidPermissionException::invalid('FieldAuthorizer', sprintf('field "%s" is declared twice.', $rule->field));
            }

            $readable = $rule->read === null || $rule->read->evaluate($this->engine, $context, $record)->allowed;
            $writable = $readable && ($rule->write === null || $rule->write->evaluate($this->engine, $context, $record)->allowed);

            $decisions[$rule->field] = match (true) {
                $writable => FieldAccess::Editable,
                $readable => FieldAccess::Readable,
                $rule->mask => FieldAccess::Redacted,
                default => FieldAccess::Hidden,
            };
        }

        return new FieldDecisions($decisions);
    }

    /**
     * The input restricted to editable fields. Any undeclared or non-editable key is a denial (403):
     * crafted requests fail loudly instead of being silently trimmed.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws AccessDeniedException
     */
    public function writable(array $input, FieldDecisions $decisions): array
    {
        $denied = array_values(array_filter(array_map(strval(...), array_keys($input)), static fn (string $key): bool => ! $decisions->access($key)->canWrite()));

        if ($denied !== []) {
            AuthorizationDecision::deny('fields.write', DenialReason::MissingPermission, null, $this->engine->strategy(), metadata: ['fields' => array_slice($denied, 0, 20)])->authorize();
        }

        /** @var array<string, mixed> $input */
        return $input;
    }

    /**
     * Only declared, readable fields; redacted fields replaced by the mask; hidden fields removed.
     *
     * @param  array<string, mixed>|object  $data  A model (through toArray(), so $hidden applies), an array or an object.
     * @return array<string, mixed>
     */
    public function visible(array|object $data, FieldDecisions $decisions): array
    {
        $values = match (true) {
            is_array($data) => $data,
            $data instanceof Arrayable => $data->toArray(),
            default => get_object_vars($data),
        };
        $visible = [];

        foreach ($decisions->fields as $field => $access) {
            if ($access->canRead()) {
                $visible[$field] = data_get($values, $field);
            } elseif ($access === FieldAccess::Redacted) {
                $visible[$field] = $this->config->fieldMask;
            }
        }

        return $visible;
    }
}
