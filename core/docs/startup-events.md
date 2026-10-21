# Startup events and timing

Core publishes typed events through Laravel's event dispatcher:

| Event | Payload |
| --- | --- |
| `FrameworkBooting` | none |
| `FrameworkBooted` | `result`: completed `BootstrapResult` |
| `FrameworkBootFailed` | `exception`, `durationMs` |
| `PackageBooting` | `id` |
| `PackageBooted` | `id`, `durationMs` |
| `PackageBootFailed` | `id`, `durationMs`, `exception` |
| `ExtensionActivating` | `id` |
| `ExtensionActivated` | `id`, `durationMs` |
| `ExtensionActivationFailed` | `id`, `durationMs`, `exception` |

All classes are under `Gcego\Core\Events`. Register listeners before GCEGO
boots, normally in a provider's `boot()` method:

```php
use Gcego\Core\Events\PackageBooted;
use Illuminate\Support\Facades\Event;

Event::listen(PackageBooted::class, function (PackageBooted $event): void {
    // Record application-specific metrics using $event->id and $event->durationMs.
});
```

## Event semantics

Events are synchronous observations. Exceptions from listeners are caught and
logged; they do not turn successful initialization into a failure or cause a
successful hook to run again. Laravel controls propagation within each dispatch,
so a throwing listener can prevent later listeners for that same event from
running. Required initialization belongs in package hooks or extension
activators, whose exceptions fail startup.

Completion events observe committed state: the package or extension is already
active, and `FrameworkBooted` observes the cached result and Booted lifecycle.
Framework startup events are emitted for each enabled attempt. Configuration
failure can emit `FrameworkBootFailed` before `FrameworkBooting` because valid
configuration is required to determine whether startup is enabled. Disabled
boots emit no startup events. Idempotent calls after success emit no new events.
Retries skip successful steps, so their step events are not emitted again.
Recursive startup is rejected, including attempts made by failure observers.

Failure events expose exceptions to trusted in-process listeners. Do not send
their messages to public health pages. Core's failure logging contains event
and exception class names only; diagnostics also sanitize unexpected failures.
Logging failures are isolated from initialization outcomes.

## Timing

```php
$gcego = app(Gcego\Core\Gcego::class);
$gcego->timings();               // list<StartupTiming>, also available after failure
$gcego->bootResult()?->timings;  // immutable snapshot in a completed result
$gcego->bootResult()?->toArray()['timings'];
```

Detailed timings are opt-in through `gcego.diagnostics.profiling` (default
`false`). Completion and step-failure event `durationMs` is `null` when profiling
is off, while framework total duration and phase metrics remain available.

Each timing contains `kind` (`package` or `extension`), `id`, `durationMs`, and
`succeeded`. Records follow execution order and retain failed attempts across
retries. Successful steps are not recorded twice. A package without a boot hook
still has a timing for its activation bookkeeping. Timings use the monotonic
clock and exclude observer execution. Total `BootstrapResult::durationMs`
measures the latest completed boot attempt through result creation, including
discovery and step observers but excluding the final `FrameworkBooted` dispatch;
it is not the sum of timings across retries.

Only scalar metadata is retained. Event objects and their exceptions are not
stored in telemetry. These are framework startup timings, not request metrics.
