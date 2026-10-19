<?php

declare(strict_types=1);

namespace Gcego\Forms\Persistence;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Exceptions\InvalidActionException;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Actions\Execution\ValidatedInput;
use Gcego\Actions\Handlers\CreateRecord;
use Gcego\Forms\Fields\ChoiceField;
use Gcego\Forms\FormManager;
use Gcego\Forms\Integration\FormInputResolver;
use Gcego\Forms\Options\OptionsResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Create/Edit handler for forms with many-to-many fields:
 *
 *     new Edit(new Handler(SaveRecord::class))
 *
 * Attributes are filled and saved like the built-in handlers (fill() keeps
 * $fillable/$guarded; Create sets the tenant column), then each BelongsToMany
 * field is synced with its validated, scope-checked ids, inside the action's transaction.
 */
final readonly class SaveRecord implements ActionHandler
{
    public function __construct(private CreateRecord $create, private FormManager $forms) {}

    public function handle(ActionContext $context): Model
    {
        $form = FormInputResolver::form($context->action) ?? throw InvalidActionException::invalid($context->identity(), 'SaveRecord needs a blueprint Form.');
        $model = $context->action->model() ?? throw InvalidActionException::invalid($context->identity(), 'SaveRecord needs a blueprint Source model.');
        $relations = [];

        foreach ($this->forms->schema($form)->children() as $entry) {
            $field = $entry->field;
            $relationship = $field instanceof ChoiceField ? $field->relationship() : null;

            if ($relationship !== null && OptionsResolver::related($model, $relationship)[1]) {
                $relations[$entry->name()] = $relationship->name;
            }
        }

        $input = $context->input->all();
        $attributes = array_diff_key($input, $relations);
        $record = $context->record instanceof Model
            ? tap($context->record, static fn (Model $record) => $record->fill($attributes)->save())
            : $this->create->handle($context->withInput(new ValidatedInput($attributes)));

        foreach ($relations as $field => $relation) {
            if (array_key_exists($field, $input)) {
                $related = $record->{$relation}();

                if ($related instanceof BelongsToMany) {
                    $related->sync(is_array($input[$field]) ? $input[$field] : []);
                }
            }
        }

        return $record;
    }
}
