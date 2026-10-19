# Exports and scheduled reports

## Exports

```php
app(ExportManager::class)->request($report, new ReportRequest(['level' => 'o'], DateRange::preset('last_30_days', $tz)), 'csv', $analytics->context());
```

`request()` runs these steps:

1. **Authorize:** report access, `export: true` and `exportAbility`.
2. **Check the format:** it must be in `formats:` and supported (CSV always; XLSX with openspout).
3. **Check concurrency:** the user's running exports must stay within `max_concurrent`.
4. **Count the rows:** at most `exports.max_rows`.
5. **Generate:** in the request when the report has at most `sync_rows` rows; otherwise `GenerateAnalyticsReport` is queued after the commit, on `exports.queue`, with retries and backoff.

Generation re-authorizes the owner in the export's tenant and reads the report in `exports.chunk` pages, so memory stays bounded. It writes to a temporary file and stores the result on `exports.disk` at `exports.directory/<tenant hash>/<ulid>.<ext>`. The owner is notified when a queued export is ready or failed.

**Security:**

- Text cells starting with `= + - @`, tab or carriage return are prefixed with `'`.
- Columns on fields the owner may not use are left out, and suppressed groups export as "Hidden (small group)".
- Downloads go through the `gcego.analytics.exports.download` route: signed and expiring, and still checked when followed. The downloader must be signed in, in the same tenant, the owner or a listed recipient, and still able to see the report. Responses are `attachment; nosniff; no-store`.
- `gcego:analytics:prune-exports` (hourly) deletes expired files.

PDF is not provided: no PDF library is approved in this repository.

## Scheduled reports

```php
app(ReportScheduleManager::class)->create($report, $analytics->context(), 'weekly', '07:30', [$headTeacher], ['level' => 'o'], 'last_7_days', 'csv', day: 1);
```

- **Creating:** the owner must be allowed to schedule (`schedulable: true` plus export access). Each recipient must be a member of the tenant and allowed to see the report. The period is a relative preset, and filters are validated.
- **Running:** `gcego:analytics:run-schedules` (every five minutes) claims each due schedule atomically, moving its next run first, and queues `DeliverScheduledReport`. That job is unique per run.
- **Delivering:** each run re-verifies the owner as a member and still allowed. If not, the schedule is paused (`last_status = 'paused: access'`). The run then generates the export, filters recipients again (skipped ones are counted in `last_status`), and notifies the owner and eligible recipients with signed links. Nothing is attached.
- **Timing:** next runs are computed in the schedule's time zone; the database stores them in the application's.
