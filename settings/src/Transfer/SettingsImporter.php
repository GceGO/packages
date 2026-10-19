<?php

declare(strict_types=1);

namespace Gcego\Settings\Transfer;

use Gcego\Settings\Contracts\SettingsAuthorizer;
use Gcego\Settings\Contracts\SettingValidator;
use Gcego\Settings\Definitions\SettingDefinition;
use Gcego\Settings\Definitions\SettingsDefinitions;
use Gcego\Settings\Exceptions\InvalidSettingException;
use Gcego\Settings\Exceptions\SettingAuthorizationException;
use Gcego\Settings\Exceptions\SettingConflictException;
use Gcego\Settings\History\ChangeType;
use Gcego\Settings\Resolution\SettingsReader;
use Gcego\Settings\Scopes\ScopeContext;
use Gcego\Settings\Security\SettingsActor;
use Gcego\Settings\SettingsConfig;
use Gcego\Settings\Types\SettingCaster;
use Gcego\Settings\Writing\SettingsWriter;
use Gcego\Settings\Writing\SettingsWriteResult;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Imports a SettingsExporter document into one scope instance, in two steps:
 *
 * - preview(): schema check (format, version, at most
 *   gcego-settings.transfer.max_settings entries), then per key: registered,
 *   not sensitive (secrets are never imported), allowed at this scope,
 *   changeable by the importer, valid; and a conflict check against what
 *   is stored;
 * - import(): the same checks again, then one atomic write (change type
 *   "imported") that refuses everything when any row is refused, when an
 *   existing override would be replaced without $overwrite, or when a
 *   stored value changed since the preview.
 *
 * Needs the import ability on top of the right to change each setting.
 */
final readonly class SettingsImporter
{
    public function __construct(
        private SettingsDefinitions $definitions,
        private SettingsAuthorizer $authorizer,
        private SettingValidator $validator,
        private SettingsReader $reader,
        private SettingsWriter $writer,
        private SettingsConfig $config,
        private SettingCaster $caster = new SettingCaster,
    ) {}

    /**
     * @param  array<array-key, mixed>  $document
     *
     * @throws SettingAuthorizationException|InvalidSettingException
     */
    public function preview(array $document, ScopeContext $context, ?Authenticatable $user): ImportPreview
    {
        if (! $this->authorizer->canImport($user, $context)) {
            throw SettingAuthorizationException::for('import');
        }

        $entries = $this->entries($document);
        $rows = $candidates = $definitions = [];

        foreach ($entries as $key => $entry) {
            $definition = is_string($key) && $this->definitions->has($key) ? $this->definitions->definition($key) : null;
            $reason = match (true) {
                $definition === null => 'unknown',
                $definition->sensitive => 'sensitive',
                ! $definition->allows($context->target) => 'scope',
                ! $this->authorizer->canUpdate($user, $definition, $context) => 'forbidden',
                ! is_array($entry) || ! array_key_exists('value', $entry) => 'invalid',
                isset($entry['type']) && $entry['type'] !== $definition->type->value => 'type',
                default => null,
            };

            if ($reason !== null || $definition === null || ! is_array($entry)) {
                $rows[] = ['key' => (string) $key, 'label' => $definition?->label() ?? (string) $key, 'status' => 'refused', 'reason' => (string) __('gcego-settings::messages.import.'.$reason)];

                continue;
            }

            $candidates[$definition->key] = $entry['value'];
            $definitions[$definition->key] = $definition;
        }

        $validation = $this->validator->validate($candidates, $definitions, $context);
        $current = $this->reader->resolveMany(array_values($definitions), $context);
        $values = $versions = [];

        foreach ($definitions as $key => $definition) {
            if (isset($validation->errors[$key])) {
                $rows[] = ['key' => $key, 'label' => $definition->label(), 'status' => 'refused', 'reason' => $validation->errors[$key][0] ?? null];

                continue;
            }

            $resolved = $current[$key];
            $versions[$key] = $resolved->version;
            $values[$key] = $validation->values[$key];
            $status = ! $resolved->isOverridden() ? 'create' : ($this->same($definition, $resolved->value, $validation->values[$key]) ? 'unchanged' : 'update');
            $rows[] = ['key' => $key, 'label' => $definition->label(), 'status' => $status, 'reason' => null];
        }

        return new ImportPreview($rows, $values, $versions);
    }

    /**
     * @param  array<array-key, mixed>  $document
     *
     * @throws SettingAuthorizationException|InvalidSettingException|SettingConflictException
     */
    public function import(array $document, ScopeContext $context, Authenticatable $user, bool $overwrite = false): SettingsWriteResult
    {
        $preview = $this->preview($document, $context, $user);

        if ($preview->hasRefusals()) {
            $errors = [];

            foreach ($preview->rows as $row) {
                if ($row['status'] === 'refused') {
                    $errors[$row['key']] = [(string) $row['reason']];
                }
            }

            throw InvalidSettingException::values($errors);
        }

        if ($preview->hasConflicts() && ! $overwrite) {
            throw SettingConflictException::for($preview->keysWith('update'));
        }

        $values = array_diff_key($preview->values, array_flip($preview->keysWith('unchanged')));

        return $this->writer->write($context, $values, SettingsActor::user($user), array_intersect_key($preview->versions, $values), ChangeType::Imported);
    }

    /**
     * @param  array<array-key, mixed>  $document
     * @return array<array-key, mixed>
     *
     * @throws InvalidSettingException
     */
    private function entries(array $document): array
    {
        if (($document['format'] ?? null) !== SettingsExporter::Format || ($document['version'] ?? null) !== SettingsExporter::Version || ! is_array($document['settings'] ?? null)) {
            throw InvalidSettingException::definition('The import', sprintf('is not a "%s" document of version %d.', SettingsExporter::Format, SettingsExporter::Version));
        }

        if (count($document['settings']) > $this->config->maxImport) {
            throw InvalidSettingException::definition('The import', sprintf('has more than %d settings.', $this->config->maxImport));
        }

        return $document['settings'];
    }

    private function same(SettingDefinition $definition, mixed $left, mixed $right): bool
    {
        return $this->caster->same($definition->type, $left, $right);
    }
}
