# Composition

## Children

```php
$dashboard = new Component(
    new Property(Component::Name, 'dashboard'),
    new Component(new Property(Component::Name, 'statistics')),
    new Component(new Property(Component::Name, 'activity')),
);
```

Children are any `ComponentInterface` instances. Their order is preserved.
A schema restricts children with `withAllowedChildren()` and `withoutChildren()`
(see [validation](validation.md)).

## Configuration nodes

Some objects configure a component without being rendered as a child, for
example a future styling modifier. They implement `Contracts\ConfigurationNode`:

```php
use Gcego\Components\Contracts\ConfigurationNode;

final readonly class Style implements ConfigurationNode
{
    public function __construct(public string $classes) {}

    public function normalize(): array
    {
        return ['classes' => $this->classes];
    }
}

$card = new Component(
    new Property(Component::Name, 'card'),
    new Style('rounded-lg shadow'),
    new Component(new Property(Component::Name, 'body')),
);

$card->childCount();                                  // 1
$card->configurations()->first(Style::class)->classes; // 'rounded-lg shadow'
```

Configuration nodes never appear in `children()`, child-count rules, trees or
traversal. They are classified by interface, never by namespace or naming. The
generic `Component` accepts any configuration node. Schemas restrict them with
`withAllowedConfigurations([Style::class])`, or forbid them all with
`withAllowedConfigurations([])`.

## Paths

Every occurrence in a tree gets a deterministic `ComponentPath`:

- the component's name, if it matches `[A-Za-z0-9_-]+`;
- `name#index`, if an earlier sibling already uses that name;
- `#index`, if the name is missing or is not a simple identifier.

Segments are joined with `.`, as in `dashboard.statistics` or `#0.#2`.

## `ComponentTree`

```php
$tree = $dashboard->tree();

$tree->count();                              // 3 nodes, root included
$tree->descendantCount();                    // 2
$tree->descendantCount('dashboard.statistics'); // 0
$tree->depth();                              // 1 (a lone root has depth 0)
$tree->find('dashboard.activity');           // ?TreeNode
$tree->contains('dashboard.activity');       // bool
$tree->findById('main-table');               // first occurrence with that explicit id
$tree->findByType('tables.columns.text');    // by type identifier...
$tree->findByType(Text::class);              // ...or by class / interface
$tree->parentOf('dashboard.activity');       // ?TreeNode
$tree->childrenOf('dashboard');              // list<TreeNode>
$tree->depthFirst();                         // list<TreeNode>, pre-order
$tree->breadthFirst();                       // list<TreeNode>
$tree->duplicateIds();                       // ['dup' => ['root.a', 'root.b']]
foreach ($tree as $node) { /* pre-order */ }
```

The index is built lazily, once, the first time it is needed.

A `TreeNode` exposes `component`, `path`, `depth`, `index` (its position among
its siblings), `parentPath`, `isRoot()` and `identity()`.

## Reuse

Components are immutable and hold no parent pointers, so one instance may
appear at several positions:

```php
$shared = new Component(new Property(Component::Name, 'shared'));
$root = new Component(new Property(Component::Name, 'root'), $shared, $shared);

$root->tree()->find('root.shared');   // first occurrence
$root->tree()->find('root.shared#1'); // second occurrence
```

A reused instance that carries an explicit id fails validation with
`DuplicateId`.

## `ComponentWalker`

```php
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Traversal\TraversalOrder;
use Gcego\Components\Traversal\Visit;

$walker = new ComponentWalker; // or new ComponentWalker(maxDepth: 64)

foreach ($walker->preOrder($root) as $node) {}
foreach ($walker->postOrder($root) as $node) {}
foreach ($walker->breadthFirst($root) as $node) {}
foreach ($walker->traverse($root, TraversalOrder::BreadthFirst) as $node) {}
foreach ($walker->ofType($root, Text::class) as $node) {}

$walker->walk($root, function (TreeNode $node): ?Visit {
    if ($node->component instanceof Text) {
        return Visit::SkipChildren;   // ignored in post-order
    }

    return null;                      // continue; return Visit::Stop to end early
});
```

All traversals are lazy generators, so breaking out of a `foreach` stops the
walk. They are iterative, so deep trees cannot overflow the call stack. They
throw `InvalidChildException` when a component appears among its own ancestors
or when nesting exceeds `maxDepth`.
