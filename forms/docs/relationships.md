# Relationship options

```php
new Select('category_id', 'Category', new Relationship('category'))                     // BelongsTo
new CheckboxList('tags', 'Tags', new Relationship('tags', title: 'label'))               // BelongsToMany
new Select('teacher_id', new Relationship('teacher', query: fn (Builder $q) => $q->where('active', true)))
```

## Rules

| Topic | Details |
| --- | --- |
| Supported relations | Only `BelongsTo` and `BelongsToMany`, on the blueprint's `Source` model. Relation names are validated with gcego/data's `RelationPathValidator`, and the relation method is checked by reflection, never called with input. |
| How options are read | `DataManager::query()` runs as the current user. That means the `viewAny` policy, tenant scoping and data scopes of the related model all apply. A user who may not list the related records gets no options. |
| Limits and order | Options are sorted by the title column and capped at `gcego-forms.options.limit` (≤ 100). |
| Submitted values | They are re-checked with the same scoped query restricted to the submitted keys. Another tenant's record, an inactive record, or an id that doesn't exist all fail validation. |
| Saving | BelongsTo values are written to the foreign key. BelongsToMany values are synced by `Persistence\SaveRecord`: `new Edit(new Handler(SaveRecord::class))`. |
| Bare forms | A `Form` rendered outside a blueprint has no model, so relationship fields throw a clear `InvalidFieldException`. |

## Other option sources

```php
new Options(['science' => 'Science', 'arts' => 'Arts'])
new Options(Kind::class)                                // backed enum; label() is used when defined
new Options(fn (FormState $state, ?string $model) => [...], dependsOn: ['kind'])
new Options(SubjectOptions::class)                      // implements Contracts\OptionsProvider
```

An `OptionsProvider` implements:
- `options(FormState, ?string $model)`;
- `allows(array $values, FormState, ?string $model)`, so membership can be
  checked without loading every option;
- `dependencies()`.
