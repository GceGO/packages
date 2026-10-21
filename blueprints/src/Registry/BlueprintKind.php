<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Registry;

use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Validation\BlueprintValidator;
use Gcego\Registry\Contracts\DefinitionKind;
use InvalidArgumentException;

/**
 * The gcego/registry definition kind for blueprints.
 *
 * gcego/blueprints owns the "blueprint" kind ("blueprint:subjects"); the
 * "module" kind belongs to gcego/modules. Validation is the
 * BlueprintValidator's reference checks: the structure was already
 * validated by Blueprint::define().
 */
final readonly class BlueprintKind implements DefinitionKind
{
    public const string Id = 'blueprint';

    public function __construct(
        private BlueprintValidator $validator = new BlueprintValidator,
    ) {}

    public function id(): string
    {
        return self::Id;
    }

    public function contract(): string
    {
        return BlueprintInterface::class;
    }

    public function name(object $definition): string
    {
        return $this->blueprint($definition)->name();
    }

    public function validate(object $definition): array
    {
        return $this->validator->validate($this->blueprint($definition))->messages();
    }

    /**
     * @return array{title: string, slug: string, description: string|null, icon: string|null, model: string|null, policy: string|null, table: string|null, form: string|null, infolist: string|null, definitions: string}
     */
    public function metadata(object $definition): array
    {
        $blueprint = $this->blueprint($definition);

        return [
            'title' => $blueprint->title(),
            'slug' => $blueprint->slug(),
            'description' => $blueprint->description(),
            'icon' => $blueprint->icon(),
            'model' => $blueprint->model(),
            'policy' => $blueprint->policy(),
            'table' => $blueprint->table(),
            'form' => $blueprint->form(),
            'infolist' => $blueprint->infolist(),
            'definitions' => implode(',', $blueprint->keys()),
        ];
    }

    private function blueprint(object $definition): BlueprintInterface
    {
        return $definition instanceof BlueprintInterface
            ? $definition
            : throw new InvalidArgumentException(sprintf('Expected a blueprint, got %s.', $definition::class));
    }
}
