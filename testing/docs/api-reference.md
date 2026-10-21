# API reference

Generated from the source with reflection. Testable assertions return the testable, so they chain; failures are PHPUnit failures. Classes are under `Gcego\Testing`; types are shown by short name.

## Entry point

### `GcegoTest`

The entry point of the toolkit.

- `static manager(): TestingManager`
- `static component(ComponentInterface $component): ComponentTestable`
- `static blueprint(BlueprintInterface $blueprint): BlueprintTestable`
- `static registry(): RegistryTestable`
- `static runtime(): RuntimeTestable`
- `static styling(ComponentInterface $component, ?string $theme = null): StylingTestable`
- `static data(Source|SourceDefinition $source): DataTestable`
- `static security(): SecurityTestable`
- `static tenant(TenantFixture|string|int $tenant): TenantTestable`
- `static action(string $definition, string $action): ActionTestable`
- `static form(Form $form, ?string $model = null): FormTestable`
- `static table(Table|string $table): TableTestable`
- `static infolist(string $definition): InfolistTestable`
- `static widget(string $identity): WidgetTestable`
- `static layout(LayoutComponent|string $layout): LayoutTestable`
- `static navigation(string $navigation): NavigationTestable`
- `static page(string $page): PageTestable`
- `static module(string $module): ModuleTestable`
- `static panel(string $panel): PanelTestable`
- `static notification(DispatchResult $result): NotificationTestable`
- `static inbox(Model $user, string|int|null $tenant = null, ?string $panel = null): InboxTestable`
- `static media(): MediaTestable`
- `static workflow(WorkflowInstance|Workflow $workflow): WorkflowTestable|WorkflowDefinitionTestable`
- `static analytics(ComponentInterface $definition): AnalyticsTestable`
- `static livewire(string $component, array $parameters = []): LivewireTestAdapter`
- `static livewireRequests(): LivewireRequests`
- `static html(string $html): HtmlTestable`
- `static accessibility(string $html): AccessibilityTestable`
- `static performance(): PerformanceTestable`
- `static contracts(): ContractTestable`
- `static fixtures(): FixtureManager`
- `static inspector(): TestingInspector`
- `static compatibility(): PackageCompatibility`
- `static reset(): void`

## Testables

### `Testables\AccessibilityTestable`

Static accessibility checks on rendered markup, and optionally axe-core.

- `result(): AccessibilityResult`
- `assertAccessible(array $except = []): AccessibilityTestable`
- `assertPasses(string $rule): AccessibilityTestable`
- `assertViolates(string $rule): AccessibilityTestable`
- `assertFormLabels(): AccessibilityTestable`
- `assertHeadingOrder(): AccessibilityTestable`
- `assertTableHeaders(): AccessibilityTestable`
- `assertButtonNames(): AccessibilityTestable`
- `assertDialogNames(): AccessibilityTestable`
- `assertErrorAssociations(): AccessibilityTestable`
- `assertAccessibleInBrowser(array $tags = […], array $except = []): AccessibilityTestable`
- `browserAvailable(): bool`

### `Testables\ActionTestable`

Executes one gcego/actions action through ActionManager::execute(), the same pipeline the Livewire host uses: resolution, availability, record lookup in scope, authorization, idempotency, confirmation token, input validation, transaction, effects and the ActionCompleted event.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): ActionTestable`
- `record(Model|string|int $record): ActionTestable`
- `select(iterable $records): ActionTestable`
- `input(array $input): ActionTestable`
- `confirmed(bool $confirm = true): ActionTestable`
- `idempotencyKey(?string $key): ActionTestable`
- `execute(): ActionTestable`
- `result(): ActionResult`
- `resolved(): ResolvedAction`
- `assertSuccessful(): ActionTestable`
- `assertStatus(ActionStatus|string $status): ActionTestable`
- `assertDenied(?string $reason = null): ActionTestable`
- `assertNotFound(): ActionTestable`
- `assertValidationFailed(array $fields = []): ActionTestable`
- `assertConfirmationRequired(): ActionTestable`
- `assertFailed(?string $reason = null): ActionTestable`
- `assertReason(string $reason): ActionTestable`
- `assertMessage(string $message): ActionTestable`
- `assertRedirect(?string $url = null): ActionTestable`
- `assertBulk(?int $processed = null, ?int $skipped = null, ?int $failed = null, ?int $selected = null): ActionTestable`
- `assertAvailable(?string $placement = null): ActionTestable`
- `assertUnavailable(?string $placement = null): ActionTestable`
- `assertRequiresConfirmation(bool $required = true): ActionTestable`

### `Testables\AnalyticsTestable`

Definition checks for gcego/analytics metrics, datasets, dimensions and other analytics nodes: component validation plus each definition's own problems() (unknown fields, aggregates a field does not allow, formula references…).

- `assertValid(): AnalyticsTestable`
- `assertProblem(string $text): AnalyticsTestable`
- `assertKind(UnitEnum|string $kind): AnalyticsTestable`
- `assertDependsOn(array $metrics): AnalyticsTestable`

### `Testables\BlueprintTestable`

Assertions about a gcego/blueprints Blueprint: validity (components and composition together), the roles it fills, its parts and its identity.

- `blueprint(): BlueprintInterface`
- `assertValid(): BlueprintTestable`
- `assertInvalid(UnitEnum|string|null $code = null, ?string $role = null): BlueprintTestable`
- `assertHasComponent(string $role): BlueprintTestable`
- `assertMissingComponent(string $role): BlueprintTestable`
- `assertPartCount(string $role, int $count): BlueprintTestable`
- `assertRoles(array $roles): BlueprintTestable`
- `assertName(string $name): BlueprintTestable`
- `assertTitle(string $title): BlueprintTestable`
- `assertStableIdentity(?string $key = null): BlueprintTestable`
- `part(string $role): ComponentTestable`
- `component(): ComponentTestable`

### `Testables\ComponentTestable`

Assertions about one gcego/morfaw definition and its subtree.

- `component(): ComponentInterface`
- `inspection(): ComponentInspection`
- `assertValid(): ComponentTestable`
- `assertInvalid(BackedEnum|string|null $code = null, ?string $path = null): ComponentTestable`
- `assertType(string $type): ComponentTestable`
- `assertComponentType(string $type): ComponentTestable`
- `assertName(?string $name): ComponentTestable`
- `assertLabel(?string $label): ComponentTestable`
- `assertId(?string $id): ComponentTestable`
- `assertHasProperty(string $key): ComponentTestable`
- `assertMissingProperty(string $key): ComponentTestable`
- `assertPropertyEquals(string $key, mixed $value): ComponentTestable`
- `assertDefault(string $key, mixed $value): ComponentTestable`
- `assertRequiresProperty(string $key): ComponentTestable`
- `assertAcceptsValue(string $key, mixed $value): ComponentTestable`
- `assertRejectsValue(string $key, mixed $value): ComponentTestable`
- `assertHasChild(string $nameOrPath): ComponentTestable`
- `assertMissingChild(string $nameOrPath): ComponentTestable`
- `assertChildCount(int $count): ComponentTestable`
- `assertContainsType(string $type, ?int $times = null): ComponentTestable`
- `assertNoDuplicateIds(): ComponentTestable`
- `assertNormalized(array $subset = []): ComponentTestable`
- `child(string $nameOrPath): ComponentTestable`

### `Testables\ContractTestable`

Behaviour every implementation of a GCEGO extension contract must have, for extension authors to check their own classes against.

- `assertComponentContract(ComponentInterface $component): ContractTestable`
- `assertRendererContract(RendererInterface $renderer, ComponentInterface $component): ContractTestable`
- `assertStylingResolverContract(StylingResolver $resolver, ComponentInterface $component, string $slot = 'root'): ContractTestable`
- `assertTenantResolverContract(TenantResolver $resolver, string $tenantKey, Authenticatable $member, Authenticatable $outsider, string $unknownKey = '999999999'): ContractTestable`
- `assertPermissionProviderContract(PermissionProvider $provider, Authenticatable $user, string $granted, string $denied, AuthorizationContext $context): ContractTestable`

### `Testables\DataTestable`

Queries a gcego/data source through DataManager, as the acting user (policies and tenant scopes applied) or as the system (scopes only).

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): DataTestable`
- `asSystem(): DataTestable`
- `query(QueryInput|array $input = []): ResultTestable`
- `assertRejects(QueryInput|array $input, string $exception = 'Gcego\Data\Exceptions\InvalidQueryException'): DataTestable`
- `assertDenied(QueryInput|array $input = []): DataTestable`
- `assertFinds(string|int $key): DataTestable`
- `assertNotFound(string|int $key): DataTestable`
- `assertHealthy(): DataTestable`

### `Testables\FormTestable`

A gcego/forms Form processed by FormManager exactly as a submission is: normalized, checked against the declared fields and the acting user's field access, validated and dehydrated. Nothing is persisted.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): FormTestable`
- `record(?Model $record): FormTestable`
- `fill(array $state): FormTestable`
- `data(): array`
- `hydrated(): array`
- `assertValid(): FormTestable`
- `assertInvalid(array $fields = []): FormTestable`
- `assertState(string $path, mixed $value): FormTestable`
- `assertDropped(string $path): FormTestable`
- `assertRejectsUnknownField(?string $path = null): FormTestable`
- `assertHydrated(string $path, mixed $value): FormTestable`
- `assertNotHydrated(string $path): FormTestable`
- `assertHasField(string $path): FormTestable`
- `assertMissingField(string $path): FormTestable`
- `assertFieldLabel(string $path, string $label): FormTestable`
- `assertFieldRequired(string $path, bool $required = true): FormTestable`
- `assertFieldDisabled(string $path, bool $disabled = true): FormTestable`
- `assertFieldDefault(string $path, mixed $value): FormTestable`
- `assertFieldVisible(string $path, bool $visible = true): FormTestable`
- `assertHealthy(): FormTestable`

### `Testables\HtmlTestable`

Assertions about rendered markup: text, elements and attributes.

- `html(): string`
- `text(): string`
- `assertSee(string $value, bool $escape = true): HtmlTestable`
- `assertDontSee(string $value, bool $escape = true): HtmlTestable`
- `assertSeeText(string $value): HtmlTestable`
- `assertDontSeeText(string $value): HtmlTestable`
- `assertSeeInOrder(array $values): HtmlTestable`
- `assertElement(string $selector, ?int $count = null): HtmlTestable`
- `assertMissingElement(string $selector): HtmlTestable`
- `assertAttribute(string $selector, string $attribute, ?string $value = null): HtmlTestable`
- `accessibility(): AccessibilityTestable`
- `assertAccessible(array $except = []): HtmlTestable`

### `Testables\InboxTestable`

A user's database notifications as gcego/notifications shows them in a tenant (and panel): the same scoped repository the inbox and the notification center use.

- `titles(): array`
- `assertUnread(int $count): InboxTestable`
- `assertContains(string $title): InboxTestable`
- `assertMissing(string $title): InboxTestable`
- `assertCannotFind(string $id): InboxTestable`
- `assertChannelAuthorized(?Authenticatable $subscriber, bool $authorized = true): InboxTestable`

### `Testables\InfolistTestable`

A registered gcego/infolists infolist for one record, as the acting user sees it: the record is looked up in scope and authorized, then each entry's access level decides whether its value is read at all.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): InfolistTestable`
- `record(Model|string|int $record): InfolistTestable`
- `assertHasEntry(string $path): InfolistTestable`
- `assertEntryLabel(string $path, string $label): InfolistTestable`
- `assertValue(string $path, ?string $text): InfolistTestable`
- `assertRedacted(string $path): InfolistTestable`
- `assertEntryHidden(string $path): InfolistTestable`
- `assertReadable(string $path): InfolistTestable`
- `assertRecordNotFound(string|int $key): InfolistTestable`
- `assertForbidden(): InfolistTestable`
- `html(): HtmlTestable`
- `assertHealthy(): InfolistTestable`

### `Testables\LayoutTestable`

A gcego/layouts layout, registered ("dashboard", "layout:dashboard") or given as a component: structure, inspector findings and rendering.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): LayoutTestable`
- `component(): LayoutComponent`
- `assertHealthy(): LayoutTestable`
- `assertReports(string $check): LayoutTestable`
- `assertContains(string $class, ?int $times = null): LayoutTestable`
- `assertTabs(array $names): LayoutTestable`
- `assertAllowed(): LayoutTestable`
- `assertDenied(): LayoutTestable`
- `html(string|int|null $record = null): HtmlTestable`
- `components(): ComponentTestable`

### `Testables\MediaTestable`

gcego/media as the acting user: uploads validated by content (not by the claimed extension), the media access policy, and tenant scoping of lookups. Use Storage::fake() for the disks the collections write to.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): MediaTestable`
- `upload(UploadedFile $file, ?string $collection = null): MediaRecord`
- `assertUploadRefused(UploadedFile $file, ?string $reason = null, ?string $collection = null): MediaTestable`
- `assertCan(MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null): MediaTestable`
- `assertCannot(MediaAbility $ability, ?MediaRecord $media = null, ?Model $owner = null): MediaTestable`
- `assertNotFound(MediaRecord|string $media): MediaTestable`
- `assertFound(MediaRecord|string $media): MediaTestable`
- `assertHealthy(): MediaTestable`

### `Testables\ModuleTestable`

A gcego/modules module as compiled: its resources, the operations and pages generated for them, relations, routes and inspector findings. Opening the generated pages is PageTestable's job.

- `definition(): ModuleDefinition`
- `assertRegistered(): ModuleTestable`
- `assertHasResource(string $resource): ModuleTestable`
- `assertOperations(string $resource, array $operations): ModuleTestable`
- `assertPage(string $resource, Operation $operation, ?string $identity = null, ?string $relation = null): ModuleTestable`
- `assertRouteName(string $resource, Operation $operation, string $route): ModuleTestable`
- `assertUrl(string $resource, Operation $operation, string $url, string|int|null $record = null, array $parameters = []): ModuleTestable`
- `assertHasRelation(string $resource, string $relation): ModuleTestable`
- `assertHealthy(): ModuleTestable`

### `Testables\NavigationTestable`

A gcego/navigation navigation as the acting user sees it on a page: which items are visible (requirements re-evaluated), which one is active, their URLs, badges and the breadcrumb trail.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): NavigationTestable`
- `on(string $uri): NavigationTestable`
- `resolved(): ResolvedNavigation`
- `assertVisible(string $id): NavigationTestable`
- `assertHidden(string $id): NavigationTestable`
- `assertActive(string $id): NavigationTestable`
- `assertNoActive(): NavigationTestable`
- `assertUrl(string $id, ?string $url): NavigationTestable`
- `assertBadge(string $id, ?string $text): NavigationTestable`
- `assertBreadcrumbs(array $labels): NavigationTestable`
- `html(?string $display = null): HtmlTestable`
- `assertHealthy(): NavigationTestable`

### `Testables\NotificationTestable`

What gcego/notifications did with one send: a delivery per recipient and channel, each sent, queued, failed or skipped (with a reason such as "tenant", "policy", "preference", "duplicate" or "expired").

- `result(): DispatchResult`
- `assertSent(?string $channel = null, Model|string|null $recipient = null): NotificationTestable`
- `assertQueued(?string $channel = null, Model|string|null $recipient = null): NotificationTestable`
- `assertSkipped(?string $reason = null, ?string $channel = null, Model|string|null $recipient = null): NotificationTestable`
- `assertNothingDelivered(): NotificationTestable`
- `assertNoFailures(): NotificationTestable`
- `assertDeliveryCount(int $count): NotificationTestable`

### `Testables\PageTestable`

A gcego/pages page ("subjects.edit" or "page:subjects-edit"): its registration and route, and opening it as the acting user through PageManager::open(), which runs the production lifecycle (authorization, record lookup in scope) up to Prepared.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): PageTestable`
- `open(Model|string|int|null $record = null, array $parameters = []): PageContext`
- `assertRegistered(): PageTestable`
- `assertRouteName(string $name): PageTestable`
- `assertUrl(string $url, Model|string|int|null $record = null, array $parameters = []): PageTestable`
- `assertOpens(Model|string|int|null $record = null, array $parameters = []): PageTestable`
- `assertForbidden(Model|string|int|null $record = null, array $parameters = []): PageTestable`
- `assertNotFound(Model|string|int|null $record = null, array $parameters = []): PageTestable`
- `assertRequiresAuthentication(): PageTestable`
- `html(Model|string|int|null $record = null, array $parameters = []): HtmlTestable`
- `assertHealthy(): PageTestable`

### `Testables\PanelTestable`

A gcego/panels panel: registration, routes, membership of modules and pages, and whether the acting user may enter it (PanelAccess, the same decision the gcego.panel middleware makes on every request and Livewire update).

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): PanelTestable`
- `descriptor(): PanelDescriptor`
- `assertRegistered(): PanelTestable`
- `assertPath(string $path): PanelTestable`
- `assertHasModule(string $module): PanelTestable`
- `assertHasPage(string $page): PanelTestable`
- `assertTenancy(?string $mode): PanelTestable`
- `assertRoute(string $name): PanelTestable`
- `assertAccessible(): PanelTestable`
- `assertInaccessible(?PanelAccessResult $reason = null): PanelTestable`
- `assertHealthy(): PanelTestable`

### `Testables\RegistryTestable`

Assertions about the application's gcego/registry: what is registered and discovered, how identifiers resolve, and what is reported.

- `registry(): Registry`
- `register(object $definition, ?string $scope = null): RegistryTestable`
- `assertHas(string $identifier): RegistryTestable`
- `assertMissing(string $identifier): RegistryTestable`
- `assertResolves(string $identifier, ?string $class = null): RegistryTestable`
- `assertNotFound(string $identifier, ?string $suggestion = null): RegistryTestable`
- `assertKind(string $identifier, string $kind): RegistryTestable`
- `assertCount(int $count, ?string $kind = null): RegistryTestable`
- `assertRejectsDuplicate(object $definition, ?string $scope = null): RegistryTestable`
- `assertRejectsInvalid(object $definition, ?string $message = null): RegistryTestable`
- `assertNoFailures(): RegistryTestable`
- `assertHealthy(): RegistryTestable`
- `assertOrigin(string $origin): RegistryTestable`

### `Testables\ResultTestable`

Assertions about a gcego/data result: the rows a source or a table returned for the acting user, in order, with pagination.

- `result(): DataResult`
- `keys(): array`
- `assertCount(int $count): ResultTestable`
- `assertTotal(int $total): ResultTestable`
- `assertEmpty(): ResultTestable`
- `assertKeys(iterable $keys): ResultTestable`
- `assertContains(iterable $keys): ResultTestable`
- `assertMissing(iterable $keys): ResultTestable`
- `assertPluck(string $field, array $values): ResultTestable`
- `assertPage(int $page, ?int $perPage = null): ResultTestable`
- `assertHasMorePages(bool $more = true): ResultTestable`

### `Testables\RuntimeTestable`

Rendering through gcego/runtime: renderer resolution, rendered markup and failures. render() uses Runtime::render() (no authorization), and renderAuthorized() the same path as <x-gcego-runtime::render>.

- `render(ComponentInterface|string $target, RenderMode $mode = RenderMode, ?string $panel = null, ?string $theme = null, int $headingLevel = 1, array $metadata = [], array $state = []): HtmlTestable`
- `renderAuthorized(string $identifier, ?string $panel = null, ?string $theme = null): HtmlTestable`
- `assertRendererFor(ComponentInterface $component, string $renderer): RuntimeTestable`
- `assertMissingRenderer(ComponentInterface $tree, ?string $class = null): RuntimeTestable`
- `assertRenderable(ComponentInterface $tree): RuntimeTestable`
- `assertRenderFails(ComponentInterface|string $target, string $exception = 'Gcego\Runtime\Exceptions\RendererNotFoundException', ?string $message = null): RuntimeTestable`
- `assertHealthy(ComponentInterface|string|null $target = null): RuntimeTestable`

### `Testables\SecurityTestable`

Authorization as gcego/security decides it for the acting user: gates and policies, requirements, registered definitions, records and fields.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): SecurityTestable`
- `decision(string $ability, mixed $resource = null): AuthorizationDecision`
- `assertCan(string $ability, mixed $resource = null): SecurityTestable`
- `assertCannot(string $ability, mixed $resource = null): SecurityTestable`
- `assertAuthorized(Requirement ...$requirements): SecurityTestable`
- `assertForbidden(Requirement|AuthorizationDecision $requirement, ?DenialReason $reason = null): SecurityTestable`
- `assertConcealed(Requirement|AuthorizationDecision $requirement): SecurityTestable`
- `assertDefinitionAllowed(ComponentInterface|string $definition, ?string $path = null, mixed $resource = null): SecurityTestable`
- `assertDefinitionDenied(ComponentInterface|string $definition, ?string $path = null, mixed $resource = null, ?int $status = null): SecurityTestable`
- `assertRecordAccessible(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): SecurityTestable`
- `assertRecordHidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): SecurityTestable`
- `assertRecordForbidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): SecurityTestable`
- `assertFieldAccess(array $rules, string $field, FieldAccess $access, mixed $record = null): SecurityTestable`
- `assertWriteRejected(array $rules, array $input, mixed $record = null): SecurityTestable`
- `assertPolicyApplied(object|string $model, ?string $policy = null): SecurityTestable`
- `assertHealthy(): SecurityTestable`

### `Testables\StylingTestable`

The classes gcego/chroma actually resolves for a component's slot, through all five layers (preset, theme, slot, app override, instance).

- `theme(string $theme): StylingTestable`
- `classes(string $slot): array`
- `assertHasClasses(string $slot, array|string $classes): StylingTestable`
- `assertLacksClasses(string $slot, array|string $classes): StylingTestable`
- `assertClasses(string $slot, array|string $classes): StylingTestable`
- `assertClassesInOrder(string $slot, array|string $classes): StylingTestable`
- `assertUnknownSlot(string $slot): StylingTestable`
- `assertHealthy(): StylingTestable`

### `Testables\TableTestable`

A gcego/tables table: its declared columns and filters, and the rows it returns for the acting user through TableManager, the path TableHost uses (authorize, column access, strict state normalization, query).

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): TableTestable`
- `schema(): TableSchema`
- `assertHasColumn(string $name): TableTestable`
- `assertMissingColumn(string $name): TableTestable`
- `assertColumnLabel(string $name, string $label): TableTestable`
- `assertColumnSearchable(string $name, bool $searchable = true): TableTestable`
- `assertColumnSortable(string $name, bool $sortable = true): TableTestable`
- `assertColumnHideable(string $name, bool $hideable = true): TableTestable`
- `assertColumnVisibleByDefault(string $name, bool $visible = true): TableTestable`
- `assertHasFilter(string $name): TableTestable`
- `assertSearchable(bool $searchable = true): TableTestable`
- `assertDefaultPerPage(int $perPage): TableTestable`
- `assertHasRowAction(string $action): TableTestable`
- `assertHasBulkAction(string $action): TableTestable`
- `query(array $state = []): ResultTestable`
- `assertRejectsState(array $state): TableTestable`
- `assertForbidden(): TableTestable`
- `assertColumnHidden(string $name): TableTestable`
- `assertColumnMasked(string $name): TableTestable`
- `assertColumnReadable(string $name): TableTestable`
- `assertExportColumns(array $columns, array $state = []): TableTestable`
- `assertHealthy(): TableTestable`

### `Testables\TenantTestable`

Multi-tenant isolation from the point of view of one tenant.

- `key(): string`
- `actingAs(Authenticatable $user, ?string $guard = null): TenantTestable`
- `run(Authenticatable $user, Closure $callback): mixed`
- `assertCurrent(): TenantTestable`
- `assertCanAccessTenant(TenantFixture|string|int|null $tenant = null, ?Authenticatable $user = null): TenantTestable`
- `assertCannotAccessTenant(TenantFixture|string|int $tenant, ?Authenticatable $user = null): TenantTestable`
- `assertSees(Source|SourceDefinition $source, iterable $keys): TenantTestable`
- `assertCannotSee(Source|SourceDefinition $source, iterable $keys): TenantTestable`
- `assertActionRefused(string $definition, string $action, string|int $record, array $input = []): TenantTestable`
- `assertSelectionRefused(string $definition, string $action, array $keys): TenantTestable`
- `assertRestoresContext(Closure $callback): TenantTestable`
- `assertCleared(): TenantTestable`

### `Testables\Testable`

Base for the fluent testables: each assertion builds a Failure with the testable's subject and context, and returns the testable for chaining.



### `Testables\WidgetTestable`

A registered gcego/widgets widget ("widget:students" or "students"): authorization, then the data WidgetManager resolves for the acting user and tenant (queries go through gcego/data scopes).

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): WidgetTestable`
- `state(array $state): WidgetTestable`
- `record(string|int|null $record): WidgetTestable`
- `widget(): Widget`
- `data(): WidgetDataResult`
- `assertAuthorized(): WidgetTestable`
- `assertForbidden(): WidgetTestable`
- `assertValue(mixed $value): WidgetTestable`
- `assertPrevious(int|float|null $previous): WidgetTestable`
- `assertLabels(array $labels): WidgetTestable`
- `assertDataset(int $index, ?string $label = null, ?array $data = null): WidgetTestable`
- `assertItemCount(int $count): WidgetTestable`
- `assertRefreshEvery(?int $seconds): WidgetTestable`
- `assertRejectsState(array $state): WidgetTestable`
- `html(): HtmlTestable`
- `assertHealthy(): WidgetTestable`

### `Testables\WorkflowDefinitionTestable`

A gcego/workflows definition before it runs: structural validation, reachability and transitions.

- `assertValid(): WorkflowDefinitionTestable`
- `assertInitialState(string $state): WorkflowDefinitionTestable`
- `assertEveryStateReachable(): WorkflowDefinitionTestable`
- `assertTransition(string $name, string $from, ?string $to = null): WorkflowDefinitionTestable`

### `Testables\WorkflowTestable`

A running gcego/workflows instance: its state and status, the append-only history, which transitions a person may take now, and how the engine refuses commands (reason codes, 403, 404 across tenants, 409 when stale).

- `instance(): WorkflowInstance`
- `assertState(string $state): WorkflowTestable`
- `assertStatus(InstanceStatus $status): WorkflowTestable`
- `assertHistory(array $types, bool $exact = true): WorkflowTestable`
- `assertTransitionAvailable(string $transition, Model $user): WorkflowTestable`
- `assertTransitionUnavailable(string $transition, Model $user, ?string $reason = null): WorkflowTestable`
- `assertRefused(Closure $command, ?string $reason = null): WorkflowTestable`

## Livewire

### `Livewire\LivewireTestAdapter`

Mounts a Livewire component with Livewire's own testing API, as the user and tenant you choose. test() returns Livewire's Testable, so every Livewire assertion (assertSee, assertSet, assertHasErrors, assertDispatched, assertRedirect, assertForbidden…) is available as is.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): LivewireTestAdapter`
- `test(): Testable`
- `assertSee(string $text): Testable`
- `assertMountRefused(int $status): LivewireTestAdapter`
- `assertLocked(string $property, mixed $value): LivewireTestAdapter`
- `assertRequestRefused(TestResponse $response): LivewireTestAdapter`

### `Livewire\LivewireRequests`

Real Livewire update requests: a POST to Livewire's update endpoint with a snapshot taken from rendered HTML, through the HTTP kernel, so persistent middleware (tenant resolution, panel access), checksums, locked properties and hydration all run as they do in a browser.

- `static snapshot(string $html, string $name): string`
- `static snapshots(string $html, string $name): array`
- `static forge(string $snapshot, Closure|array $changes): string`
- `update(string $snapshot, array $calls = [], array $updates = [], array $session = []): TestResponse`
- `call(string $snapshot, string $method, mixed ...$params): TestResponse`

## Performance

### `Performance\PerformanceTestable`

Assertions about one measured callback.

- `measure(Closure $callback): PerformanceTestable`
- `measurement(): Measurement`
- `result(): mixed`
- `assertQueryCount(int $count): PerformanceTestable`
- `assertQueryCountAtMost(int $limit): PerformanceTestable`
- `assertNoQueries(): PerformanceTestable`
- `assertNoDuplicateQueries(): PerformanceTestable`
- `assertNoRepeatedQueries(?int $threshold = null): PerformanceTestable`
- `assertMemoryBelow(int $bytes): PerformanceTestable`
- `assertDurationBelow(float $milliseconds): PerformanceTestable`
- `assertPayloadBelow(int $bytes): PerformanceTestable`
- `assertCachedAfterFirstRun(Closure $callback, int $queries = 0): PerformanceTestable`

### `Performance\Measurement`

What one measured callback did: its queries, how much memory it allocated, how long it took and what it returned.

- `queryCount(): int`
- `duplicates(): array`
- `repeatedShapes(int $threshold): array`
- `payloadBytes(): int`
- `sql(): array`

## Accessibility

### `Accessibility\StaticAccessibilityChecker`

Accessibility rules that can be decided from markup alone.

- `check(string $markup, ?array $rules = null): AccessibilityResult`
- `accessibleName(DOMElement $element): string`

`Rules`: `form-label`, `image-alt`, `button-name`, `link-name`, `heading-order`, `empty-heading`, `table-headers`, `dialog-name`, `duplicate-id`, `aria-reference`, `error-association`, `positive-tabindex`, `aria-hidden-focus`, `landmark-name`

### `Accessibility\BrowserAccessibility`

axe-core in headless Chromium, through Playwright.

- `available(): bool`
- `unavailableReason(): ?string`
- `auditHtml(string $html, array $tags = []): AccessibilityResult`
- `auditUrl(string $url, array $tags = []): AccessibilityResult`
- `modules(): ?string`

### `Accessibility\AccessibilityResult`

The violations one audit found, and which engine produced them: "static" (markup rules in PHP) or "axe" (axe-core in a browser).

- `passes(): bool`
- `violations(?string $rule = null): array`
- `without(array $rules): AccessibilityResult`
- `formatted(): array`
- `toArray(): array`

## Fixtures and factories

### `Fixtures\FixtureManager`

Creates and tracks a test's fixtures: temporary workspaces, registry fixtures, representative definitions, users and tenants. cleanup() removes every workspace and runs every registered cleanup callback, in reverse order, even when one of them fails.

- `workspace(): Workspace`
- `registry(): RegistryFixture`
- `definitions(): DefinitionFactory`
- `users(): TestUserFactory`
- `tenants(): TestTenantFactory`
- `onCleanup(Closure $cleanup): void`
- `workspaces(): array`
- `cleanup(): void`

### `Fixtures\Workspace`

A temporary directory owned by one test: unique per process and parallel-testing token, never inside the application, and removed by remove() (the FixtureManager removes every workspace on cleanup).

- `static create(?string $base = null): Workspace`
- `path(string $relative = ''): string`
- `directory(string $relative): string`
- `file(string $relative, string $contents): string`
- `php(string $relative, string $expression, array $imports = []): string`
- `exists(): bool`
- `remove(): void`

### `Fixtures\RegistryFixture`

Definition files discovered from a temporary workspace instead of the application's app/GCEGo directories: the application's own definitions are never read or changed, and the files disappear on cleanup.

- `definition(string $relative, string $expression, array $imports = []): RegistryFixture`
- `root(string $directory, string $kind = 'blueprint', string $entrypoint = 'file', ?string $scope = null): RegistryFixture`
- `config(): array`
- `apply(Container $container): void`
- `workspace(): Workspace`

### `Fixtures\DefinitionFactory`

Representative definitions built with each package's public API, for tests that need "a form", "a table" or "a resource" and not a specific one. Every method needs the package that defines the class; a missing package raises MissingPackageException naming it.

- `textInput(string $name = 'name', string $label = 'Name', bool $required = true): TextInput`
- `select(string $name = 'status', string $label = 'Status', array $options = […]): Select`
- `form(ComponentInterface ...$fields): Form`
- `column(string $name = 'name', string $label = 'Name', bool $searchable = true, bool $sortable = true): Text`
- `table(ComponentInterface ...$columns): Table`
- `source(string $model, array $search = […]): Source`
- `infolist(ComponentInterface ...$entries): Infolist`
- `action(string $name = 'publish', string $label = 'Publish', string $ability = 'update', ?Closure $handler = null): Action`
- `resource(string $name, string $model): Blueprint`
- `stat(string $label = 'Students', string|int|float $value = 0, ?Requirement $requirement = null): Stat`
- `layout(string $name = 'dashboard'): Layout`
- `navigation(string $name = 'admin', array $items = […]): Navigation`
- `page(string $name = 'overview', string $title = 'Overview'): DashboardPage`
- `module(string $name, string $model): Module`
- `panel(string $name = 'admin', string $path = 'admin', ?Requirement $access = null, array $modules = []): Panel`

### `Factories\TestUserFactory`

Creates users with the application's own model.

- `create(array $attributes = []): Authenticatable`
- `many(int $count, array $attributes = []): array`

### `Factories\TestTenantFactory`

Creates tenants and memberships through the application's TenantFixtureProvider (gcego-testing.fixtures.tenants).

- `create(array $attributes = []): TenantFixture`
- `join(Authenticatable $user, TenantFixture $tenant, array $roles = [], array $permissions = []): Authenticatable`
- `available(): bool`

## Environment

### `Environment\TestingEnvironment`

Who is acting, in which tenant, inside one simulated request.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): void`
- `guest(): void`
- `enterTenant(string $tenant): void`
- `leaveTenant(): void`
- `currentTenant(): ?string`
- `user(): ?Authenticatable`
- `newRequest(): void`
- `onPage(string $uri, array $forget = []): void`

### `Environment\TestStateResetter`

Clears what one test can leave behind for the next inside the same application instance (Laravel normally boots a fresh application per test; long-running processes and suites that reuse one need this).

- `using(Closure $resetter): TestStateResetter`
- `reset(): void`

### `Environment\TestApplicationFactory`

The service providers and configuration an isolated GCEGO test application needs, following the conventions of the packages' own suites: in-memory SQLite, array cache and session, sync queue, array mail, registry discovery and its cache off (definitions are registered explicitly), and a per-process cache prefix for parallel runs.

- `static providers(?array $only = null): array`
- `static defaults(): array`
- `static configure(Repository $config, array $overrides = []): void`

### `Environment\PackageTestCase`

A ready Orchestra Testbench test case for GCEGO packages and extensions: every installed GCEGO provider, the isolated configuration of TestApplicationFactory, the toolkit's assertions, and state reset after each test.

- `assertGcegoComponentValid(ComponentInterface $component): void`
- `assertGcegoComponentInvalid(ComponentInterface $component, ?string $code = null, ?string $path = null): void`
- `assertGcegoBlueprintValid(BlueprintInterface $blueprint): void`
- `assertGcegoBlueprintHas(BlueprintInterface $blueprint, string $role): void`
- `assertGcegoAccessible(string $html, array $except = []): void`
- `assertGcegoQueryCountAtMost(int $limit, Closure $callback): void`
- `assertGcegoNoRepeatedQueries(Closure $callback, ?int $threshold = null): void`
- `assertGcegoCan(string $ability, mixed $resource = null): void`
- `assertGcegoCannot(string $ability, mixed $resource = null): void`
- `assertGcegoRecordHidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void`
- `assertGcegoRecordForbidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void`
- `assertGcegoCannotAccessTenant(TenantFixture|string|int $tenant): void`
- `assertGcegoTenantIsolated(Source|SourceDefinition $source, iterable $keys): void`

## Diagnostics and compatibility

### `Diagnostics\TestingInspector`

Reports whether the test environment is usable and isolated: testing dependencies, database, cache and queue drivers, registry caching, tenancy, fixtures, parallel testing, browser tools and the GCEGO packages. Read-only: it changes nothing.

- `inspect(): TestingReport`

### `Diagnostics\TestingReport`

- `isHealthy(): bool`
- `errors(): array`
- `check(string $check): ?TestingDiagnostic`
- `toArray(): array`

### `Compatibility\PackageCompatibility`

Checks the platform and the GCEGO packages the toolkit integrates with, reading Composer's installed versions (never a hard-coded list of versions from elsewhere).

- `check(): array`
- `failures(): array`
- `version(string $package): ?string`

### `Diagnostics\AssertionFailureFormatter`

Turns a Failure into the text PHPUnit prints.

- `format(Failure $failure): string`
- `scalar(mixed $value): string`

### `Diagnostics\Redactor`

Replaces values under sensitive keys before they reach failure output.

- `isSensitive(string|int $key): bool`
- `redact(array $values, int $depth = 0): array`
- `value(mixed $value, int $depth = 0): mixed`
- `scrub(Stringable|string $text, array $secrets): string`

### `Assertions\Verifier`

The single place every toolkit assertion passes through.

- `that(bool $passed, Failure|Closure $failure): void`
- `caught(Closure $callback): ?Throwable`
- `rootOf(?Throwable $exception, string $class): ?Throwable`
- `formatter(): AssertionFailureFormatter`

## PHPUnit traits

### `Concerns\InteractsWithGcego`

PHPUnit-style assertions for any Laravel or Testbench test case. They run the same testables as GcegoTest, so failures read the same.

- `gcego(): TestingManager` (protected)
- `resetGcegoState(): void` (protected)
- `assertGcegoComponentValid(ComponentInterface $component): void`
- `assertGcegoComponentInvalid(ComponentInterface $component, ?string $code = null, ?string $path = null): void`
- `assertGcegoBlueprintValid(BlueprintInterface $blueprint): void`
- `assertGcegoBlueprintHas(BlueprintInterface $blueprint, string $role): void`
- `assertGcegoAccessible(string $html, array $except = []): void`
- `assertGcegoQueryCountAtMost(int $limit, Closure $callback): void`
- `assertGcegoNoRepeatedQueries(Closure $callback, ?int $threshold = null): void`
- `gcegoFixtures(): FixtureManager` (protected)
- `gcegoWorkspace(): Workspace` (protected)
- `gcegoDefinitions(): DefinitionFactory` (protected)
- `createGcegoUser(array $attributes = []): Authenticatable` (protected)
- `gcegoLivewire(string $component, array $parameters = []): Testable` (protected)
- `gcegoSnapshot(string $html, string $name): string` (protected)
- `gcegoLivewireUpdate(string $snapshot, array $calls = [], array $updates = [], array $session = []): TestResponse` (protected)
- `actingAsGcego(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): static` (protected)
- `newGcegoRequest(): void` (protected)
- `assertGcegoCan(string $ability, mixed $resource = null): void`
- `assertGcegoCannot(string $ability, mixed $resource = null): void`
- `assertGcegoRecordHidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void`
- `assertGcegoRecordForbidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void`
- `enterGcegoTenant(TenantFixture|string|int $tenant): void` (protected)
- `leaveGcegoTenant(): void` (protected)
- `assertGcegoCannotAccessTenant(TenantFixture|string|int $tenant): void`
- `assertGcegoTenantIsolated(Source|SourceDefinition $source, iterable $keys): void`

### `Concerns\InteractsWithGcegoSecurity`

- `actingAsGcego(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): static` (protected)
- `newGcegoRequest(): void` (protected)
- `assertGcegoCan(string $ability, mixed $resource = null): void`
- `assertGcegoCannot(string $ability, mixed $resource = null): void`
- `assertGcegoRecordHidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void`
- `assertGcegoRecordForbidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): void`

### `Concerns\InteractsWithGcegoTenancy`

- `enterGcegoTenant(TenantFixture|string|int $tenant): void` (protected)
- `leaveGcegoTenant(): void` (protected)
- `assertGcegoCannotAccessTenant(TenantFixture|string|int $tenant): void`
- `assertGcegoTenantIsolated(Source|SourceDefinition $source, iterable $keys): void`

### `Concerns\InteractsWithGcegoLivewire`

- `gcegoLivewire(string $component, array $parameters = []): Testable` (protected)
- `gcegoSnapshot(string $html, string $name): string` (protected)
- `gcegoLivewireUpdate(string $snapshot, array $calls = [], array $updates = [], array $session = []): TestResponse` (protected)

### `Concerns\InteractsWithGcegoFixtures`

- `gcegoFixtures(): FixtureManager` (protected)
- `gcegoWorkspace(): Workspace` (protected)
- `gcegoDefinitions(): DefinitionFactory` (protected)
- `createGcegoUser(array $attributes = []): Authenticatable` (protected)

## Pest

### `Integrations\PestIntegration`

Optional Pest expectations over the same testables.

- `static register(): void`

## Contracts

### `Contracts\TestEnvironment`

The acting user and tenant of the current simulated request.

- `actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): void`
- `guest(): void`
- `enterTenant(string $tenant): void`
- `leaveTenant(): void`
- `currentTenant(): ?string`
- `user(): ?Authenticatable`
- `newRequest(): void`
- `onPage(string $uri, array $forget = []): void`

### `Contracts\UserFixtureProvider`

Creates test users with the application's own model and factory.

- `create(array $attributes = []): Authenticatable`

### `Contracts\TenantFixtureProvider`

Creates tenants and memberships with the application's own models.

- `create(array $attributes = []): TenantFixture`
- `join(Authenticatable $user, TenantFixture $tenant, array $roles = [], array $permissions = []): void`
