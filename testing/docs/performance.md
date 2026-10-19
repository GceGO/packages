# Performance

<!-- example:performance-guide -->
```php
use Gcego\Testing\GcegoTest;
use Gcego\Testing\Tests\Fixtures\Models\Subject;

$performance = GcegoTest::performance()->measure(fn () => GcegoTest::table('subjects')->actingAs($admin, 1)->query()->result());

$performance->assertQueryCountAtMost(10)->assertNoDuplicateQueries()->assertNoRepeatedQueries();

GcegoTest::performance()->measure(fn () => str_repeat('x', 100))->assertMemoryBelow(1_000_000)->assertPayloadBelow(1024);
```

| Assertion | Measures |
| --- | --- |
| `assertQueryCount($n)`, `assertQueryCountAtMost($n)`, `assertNoQueries()` | exact number of queries the callback ran |
| `assertNoDuplicateQueries()` | identical SQL and bindings ran more than once |
| `assertNoRepeatedQueries($threshold)` | a query shape (literals stripped) ran `$threshold` or more times, the usual N+1 signature |
| `assertMemoryBelow($bytes)` | growth of allocated memory across the call |
| `assertDurationBelow($ms)` | wall-clock time |
| `assertPayloadBelow($bytes)` | size of the result (string length, or JSON) |
| `assertCachedAfterFirstRun($callback, $queries)` | a second run makes at most `$queries` queries |

- **Recording**: queries are recorded through Laravel's `QueryExecuted` event, only while the callback runs.
- **Failures** list the SQL. The N+1 report shows each repeated shape with its count. Repeated shapes are a *heuristic*: a deliberate loop looks the same.
- **Durations** depend on the machine. Measure locally, then assert a limit several times larger, or don't assert one at all in CI.
