# Sync

Data migration and API integration suite for Drupal 10.3+ / 11.

Sync pulls records from a remote source — an HTTP API, a SOAP or OData service,
a file on disk or on an FTP/SFTP/S3 server, a Google Sheet, another database, or
even other Drupal entities — and maps each record onto a Drupal entity. It keeps
a persistent record of which source record produced which entity, so subsequent
runs update rather than duplicate, and can optionally delete entities whose
source record has disappeared.

Work is broken into queue jobs, so a sync of any size runs incrementally and
survives being interrupted.

## Table of contents

- [Concepts](#concepts)
- [Quick start](#quick-start)
- [Clients](#clients)
- [Resources](#resources)
- [Resource annotation reference](#resource-annotation-reference)
- [Overridable methods](#overridable-methods)
- [Controlling flow with exceptions](#controlling-flow-with-exceptions)
- [Change detection](#change-detection)
- [Cleanup](#cleanup)
- [Sub-resources](#sub-resources)
- [Running a sync](#running-a-sync)
- [Drush commands](#drush-commands)
- [Settings](#settings)
- [Permissions](#permissions)
- [Database schema](#database-schema)
- [Locking an entity](#locking-an-entity)
- [Performance notes](#performance-notes)
- [Upgrading](#upgrading)

## Concepts

A sync is assembled from four plugin types.

| Type | Discovery | Responsibility |
| --- | --- | --- |
| **Fetcher** | `@SyncFetcher` annotation, `Plugin/SyncFetcher` | Retrieve the raw payload from the source, one page at a time |
| **Parser** | `@SyncParser` annotation, `Plugin/SyncParser` | Turn that payload into a plain PHP array of records |
| **Client** | `MODULE.sync.client.yml` | Names a fetcher and parser, plus their settings |
| **Resource** | `@SyncResource` annotation, `Plugin/SyncResource` | Identifies each record and maps it onto an entity |

Only the resource normally needs custom PHP. The client is YAML, and the shipped
fetchers and parsers cover most sources.

Each record becomes a `SyncDataItem`, and a page of them becomes a
`SyncDataItems` collection.

`SyncDataItem` wraps the record's array and offers `get()`, `set()`, `has()`,
`empty()`, `unset()` and `toArray()`, as well as array and property access. A
nested value is addressed with an **array path** — `$item->get(['a', 'b'])`,
not `$item->get('a.b')`. Note also that the constructor converts the literal
string `'NULL'` to a real `NULL`, which matters for sources such as CSV that
cannot express null.

### Shipped fetchers

`entity`, `entity_query`, `file`, `file_upload`, `ftp`, `google_sheet`, `http`,
`nav_soap`, `odata_v4`, `s3_get_object`, `s3_list_objects`, `sftp`, `soap`,
`sql`

### Shipped parsers

`csv`, `json`, `none`, `object_to_array`, `rss`, `s3`, `stream`, `xml`

## Quick start

**1. Declare a client** in `MODULE.sync.client.yml`:

```yaml
example_articles:
  fetcher: http
  fetcher_settings:
    url: 'https://example.com/api/articles'
    page_enabled: true
    page_size: 100
  parser: json
  parser_settings:
    base_key: results
```

**2. Write a resource** in `src/Plugin/SyncResource/Articles.php`:

```php
namespace Drupal\my_module\Plugin\SyncResource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\sync\Plugin\SyncDataItem;
use Drupal\sync\Plugin\SyncResourceBase;

/**
 * @SyncResource(
 *   id = "articles",
 *   label = @Translation("Articles"),
 *   client = "example_articles",
 *   entity_type = "node",
 *   bundle = "article",
 *   cron = "2:00",
 *   cleanup = true,
 * )
 */
class Articles extends SyncResourceBase {

  protected function id(SyncDataItem $item) {
    return $item->get('uuid');
  }

  protected function processItem(EntityInterface $entity, SyncDataItem $item) {
    $entity->set('title', $item->get('title'));
    // Nested keys are addressed with an array path, not dot notation.
    $entity->set('body', $item->get(['content', 'html']));
  }

}
```

**3. Run it** from `/admin/sync`, or with `drush sync:run articles`.

The `id()` return value is the sync key. It must be stable for the lifetime of
the record, and unique within the resource — everything else follows from it.

## Clients

A client is a YAML entry keyed by client id. Every key has a default, so only
`fetcher` is strictly required.

```yaml
client_id:
  fetcher: http              # fetcher plugin id
  fetcher_settings: {}       # merged over the fetcher's defaultSettings()
  parser: json               # parser plugin id, defaults to 'none'
  parser_settings: {}        # merged over the parser's defaultSettings()
```

### Common fetcher settings

Every fetcher accepts the paging settings:

| Setting | Default | Meaning |
| --- | --- | --- |
| `page_enabled` | `false` | Whether to request more than one page |
| `page_size` | `0` | Records per page |
| `page_limit` | `0` | Maximum pages; `0` for unlimited |

Plus their own:

| Fetcher | Settings |
| --- | --- |
| `http` | `url`, `query`, `headers`, `as_content`, `page_key`, `timeout` |
| `file` | `path`, `remote` |
| `file_upload` | `path`, `file_field_title`, `extentions` |
| `ftp` | `server`, `server_port`, `username`, `password`, `filename` |
| `sftp` | `server`, `server_port`, `username`, `password`, `key_path`, `filename` |
| `sql` | `target`, `key`, `page_size` |
| `entity` | `entity_type`, `properties` |
| `entity_query` | `entity_type`, `conditions` |
| `soap` | `url`, `options`, `params`, `resource_name`, `login`, `password` |
| `odata_v4` | `login`, `password`, `resource_name`, `filters`, `page_key` |
| `nav_soap` | `filters`, `resource_segment`, `resource_function`, `resource_function_result`, `bookmark_key` |
| `google_sheet` | `credentials`, `id`, `name` |
| `s3_list_objects`, `s3_get_object` | `s` |

### Common parser settings

| Parser | Settings |
| --- | --- |
| `csv` | `header`, `delimiter`, `remove_lines` |
| `json` | `base_key` |
| `xml` | `base_key` |

### Paging

A fetcher either handles paging itself or lets the parser emulate it.

- `handlesPagination() === TRUE` — the fetcher returns exactly one page per
  call, typically via a `LIMIT`/`OFFSET`, a cursor, or a continuation token.
  `sql`, `entity_query`, `odata_v4` and `nav_soap` work this way.
- `handlesPagination() === FALSE` — the fetcher returns everything it has, and
  the `json` or `csv` parser slices out the requested page.

Emulated paging re-reads and re-parses the whole payload for every page, so its
cost grows with the square of the page count. For a large source, prefer a
fetcher that pages natively. When writing a custom fetcher for a large file,
set `protected $handlesPagination = TRUE` and track your own offset — see
[Custom fetcher state](#custom-fetcher-state).

## Resources

### The run

Building a run fetches page one, then queues:

1. `doStart` — resets counters and stamps the run start time
2. one `doProcess` job per record
3. either `doPage` (fetch and queue the next page) or `doEnd`
4. `doCleanup` instead of `doEnd` when cleanup is enabled, which queues one
   `doClean` job per stale record, then `doEnd`

Each `doProcess` job loads or creates the entity for its sync id, calls
`processItem()`, saves, and records the mapping.

### Custom fetcher state

Anything a fetcher writes into `$this->configuration` during `fetch()` is
snapshotted into the `doPage` job and restored before the next page. That makes
it the right place for a cursor, byte offset, or continuation token:

```php
protected function fetch($page_number, SyncDataItems $previous_data) {
  $offset = (int) $this->configuration['offset'];
  // ... read from $offset ...
  $this->configuration['offset'] = $new_offset;
  return $payload;
}
```

## Resource annotation reference

| Property | Default | Meaning |
| --- | --- | --- |
| `id` | — | Plugin id. Also names the queue (`sync_ID`) and the sync group |
| `label` | — | Human-readable name |
| `client` | — | Client id from a `*.sync.client.yml` |
| `entity_type` | `''` | Entity type to create or update |
| `bundle` | `''` | Bundle; falls back to `entity_type` |
| `status` | `1` | `0` hides the resource and stops it running |
| `computed` | `0` | Shows in the UI but cannot be run manually, and never runs on cron |
| `no_ui` | `0` | Active but hidden from the overview |
| `cron` | `'00:00'` | Comma-separated times of day the run is due |
| `day` | `'mon,tue,wed,thu,fri'` | Comma-separated days the run is due |
| `cleanup` | `0` | Delete entities whose source record disappeared |
| `reset` | `0` | Allow the last-run timestamp to be reset from the UI |
| `weight` | `0` | Ordering in the overview |
| `hash` | `0` | Skip source records that have not changed. See [Change detection](#change-detection) |
| `hash_version` | `1` | Salt that invalidates stored hashes; or `"auto"` |
| `verify_entities` | `1` | Require a hash's entity to still exist before trusting it |
| `build_policy` | `'append'` | What to do when a run starts with jobs still queued |

A resource runs at most once per day: once `doStart` records the run, it is not
due again until the next matching day.

### `build_policy`

| Value | Behaviour |
| --- | --- |
| `append` | Leave pending jobs in place and add the new run behind them. The historical default |
| `restart` | Discard pending jobs and start clean. Correct for a full snapshot feed, where the previous run's leftovers are stale by definition |
| `resume` | Skip building entirely and let the pending run finish. Correct for a delta feed, where every item matters |

`append` is the default for backwards compatibility, but for a source that
publishes a complete snapshot each time it lets an unfinished run accumulate
behind the next one. If your source is a full snapshot, use `restart`.

## Overridable methods

Ordered roughly by when they run.

| Method | Purpose |
| --- | --- |
| `alterFetcher(SyncFetcherInterface $fetcher)` | Adjust fetcher settings before the request |
| `alterParser(SyncParserInterface $parser)` | Adjust parser settings before parsing |
| `prepareData(array $data)` | Reshape the decoded payload before it becomes items |
| `alterItems(SyncDataItems $data)` | Alter the whole page |
| `alterItem(SyncDataItem $data)` | Alter one record, at queue time |
| `id(SyncDataItem $item)` | **Required.** The unique sync key |
| `hashSource(SyncDataItem $item)` | Narrow what participates in the change fingerprint |
| `getBundle(SyncDataItem $item)` | Choose the bundle per record |
| `getInitialValues(SyncDataItem $item)` | Property values used to adopt a pre-existing, un-synced entity on first import |
| `prepareItem(SyncDataItem $item)` | Alter one record, at process time |
| `accessEntity(EntityInterface $entity)` | Veto syncing a particular entity |
| `processItem(EntityInterface $entity, SyncDataItem $item)` | **The mapping.** Set field values here |
| `saveItem(EntityInterface $entity, SyncDataItem $item)` | Override how the entity is saved |
| `cleanupQueryAlter(SelectInterface $query, array $context)` | Narrow which records cleanup considers stale |
| `cleanItem(EntityInterface $entity, array $sync, array $context)` | Override cleanup's delete |
| `onComplete(array $context)` | Runs once, after the entire run has finished |

`alterItem()` and `prepareItem()` look similar but run at different times.
`alterItem()` runs during fetch, before the record is queued; `prepareItem()`
runs when the job is claimed. If change detection is enabled, note that `id()`
is called at both points — see the caveat under
[Change detection](#change-detection).

### `onComplete()` versus `doEnd()`

`doEnd()` re-queues itself while jobs remain, so it can run several times per
run. Anything that must happen exactly once, and only when everything is
genuinely finished — archiving a source file, notifying a remote system,
releasing a lock — belongs in `onComplete()`.

```php
protected function onComplete(array $context) {
  $this->getFetcher()->archiveSourceFile();
}
```

## Controlling flow with exceptions

Throw these from `alterItem()`, `prepareItem()` or `processItem()`.

| Exception | Effect |
| --- | --- |
| `SyncIgnoreException` | Silently skip. The exception code selects the counter: `0` skip, `1` success, `2` fail |
| `SyncSkipException` | Skip and log a warning. The sync record is still written, so cleanup will not delete the entity |
| `SyncSkipWithoutSaveException` | Skip without writing the sync record. Only safe when cleanup is off |
| `SyncFailException` | Log an error and count a failure |
| `SyncJobQueueReleaseException` | Release the queue item so the job runs again later |

## Change detection

Opt in with `hash = true`. The resource then stores a fingerprint of each source
record after a successful sync, and on the next run compares it against the
incoming record. Unchanged records are filtered out **before they are queued**,
so they cost neither a queue row nor an entity load and save.

```php
/**
 * @SyncResource(
 *   id = "articles",
 *   ...
 *   hash = true,
 *   hash_version = "auto",
 * )
 */
```

For a source where most records are stable between runs — a nightly roster, a
product catalogue, a member list — this is usually the single largest
performance win available, because the work avoided is the expensive part.

### Narrowing the fingerprint

By default the whole record is fingerprinted, so churn in a field the resource
never maps still forces a re-save. Override `hashSource()` to return only what
matters:

```php
protected function hashSource(SyncDataItem $item) {
  return [
    'title' => $item->get('title'),
    'body' => $item->get(['content', 'html']),
    // Sorted, because the source does not guarantee an order and a
    // reordering is not a change.
    'tags' => $this->sortTags($item->get('tags') ?? []),
  ];
}
```

Key order never matters — associative arrays are sorted recursively before
hashing. The order of *lists* is preserved, because for many sources list order
is itself meaningful. If your source emits a list in an unstable order, sort it
in `hashSource()`.

The positional `_sync_key` is always excluded.

### Invalidating stored hashes

When `processItem()` changes what it writes, every stored hash becomes stale:
records that did not change at the source would be skipped and never pick up the
new mapping. Two ways to handle it:

- `hash_version = "auto"` derives the salt from the resource class file, so any
  edit to the class invalidates every hash automatically. Costs one full
  re-sync per deploy that touches the file.
- `hash_version = 2` (any integer or string) invalidates when you bump it
  manually. Predictable, but easy to forget.

`drush sync:run ID --force` ignores stored hashes for a single run without
changing anything persistent.

### What it does not do

A hash-filtered run will not correct manual edits made to a synced entity in
Drupal, because from the source's point of view nothing changed. If the sync is
also expected to act as a corrector, schedule a periodic `--force` run.

### Caveats

- **`id()` must be derivable from the raw record.** The sync id is computed both
  at queue time (before `prepareItem()`) and at process time (after it). A
  resource that computes or normalizes its id inside `prepareItem()` would
  produce two different ids and silently mismatch every record. This is why
  `hash` is opt-in rather than on by default.
- **Deletions are handled**, both by `hook_entity_delete()` clearing the sync
  record and, for deletions that bypassed the entity API, by the
  `verify_entities` check. Turn `verify_entities` off only if the extra indexed
  query per page is measurably costly.
- **Skipped records still have their `changed` timestamp refreshed**, so cleanup
  does not mistake them for stale.

## Cleanup

With `cleanup = true`, a run ends by selecting every sync record for the
resource whose `changed` timestamp predates the run's start, and deleting the
matching entity. That is how records removed at the source get removed locally.

Two consequences worth knowing:

- Never throw `SyncSkipWithoutSaveException` from a resource that uses cleanup.
  It leaves the timestamp stale, and the entity gets deleted.
- Cleanup makes a partial run destructive: records not reached before the run
  ended look stale. Combine `cleanup` with a `build_policy` that suits your
  source, and give the run enough time to finish.

Override `cleanupQueryAlter()` to narrow the candidates, and `cleanItem()` to
unpublish instead of delete.

## Sub-resources

A resource can queue another resource as part of its own run:

```php
protected function processItem(EntityInterface $entity, SyncDataItem $item) {
  $this->queueSubResource('child_resource', [
    'parent_id' => $item->get('id'),
  ]);
}
```

The child's jobs are added to the parent's queue and its logging is attributed
to the parent, so the whole tree completes as one run.

## Running a sync

### Scheduled

`hook_cron()` builds every resource whose `cron` time has passed on a matching
`day`, and core cron then drains the queues. Core gives each queue a fixed
budget per cron run (30 seconds by default), which is fine for a small sync and
a hard ceiling for a large one — see [Performance notes](#performance-notes).

### The `/sync-cron/{key}` endpoint

`/sync-cron/{key}`, using the same key as core's `/cron/{key}`, returns `204`
immediately and then runs cron in a detached background process, so the request
is not held open. It falls back to running cron inline if `exec()` is
unavailable or the drush binary cannot be located.

The URL is shown on `/admin/config/system/cron`.

### Manually

`/admin/sync` lists every resource with a Run action, which executes the sync
through the Batch API with a progress bar. Fetchers implementing
`SyncFetcherFormInterface` (such as `file_upload`) get a form at
`/admin/sync/{plugin_id}/form` to supply their input first.

`/admin/sync/{plugin_id}/log` shows the log for the most recent run.

Appending `?debug=1` to a manual run dumps the first 100 parsed items instead of
processing them — useful for confirming a fetcher and parser before writing any
mapping code.

## Drush commands

### `drush sync:run <resource_id>`

Builds if due, then drains. Safe to call repeatedly on a short interval: it
takes a named lock and exits quietly if the resource is already running.

| Option | Default | Meaning |
| --- | --- | --- |
| `--build` | `auto` | `auto` builds only when due and the queue is empty; `always` always builds; `never` only drains |
| `--time-limit` | `540` | Seconds of wall clock to spend. `0` for unlimited |
| `--lease` | `120` | Seconds to lease each claimed queue item |
| `--force` | off | Ignore stored change-detection hashes for this run |
| `--max-items` | `0` | Stop after this many items. `0` for unlimited |
| `--memory-limit` | `1200M` | Stop cleanly once memory usage exceeds this |

When it runs out of time, items, or memory it stops cleanly and leaves the queue
resumable; the next invocation picks up where it left off. If the process is
killed, the in-flight item is released rather than left leased, and any lease
orphaned by a hard kill is reclaimed at the start of the next run.

### `drush sync:cron`

Builds and drains every resource that is due, plus any resource with items still
queued, against a shared deadline. This is the command to point an external
scheduler at.

```
drush sync:cron --time-limit=540
```

Accepts `--time-limit`, `--lease` and `--memory-limit`.

### `drush sync:sync <resource_id>` (alias `sync`)

The original command. Purges the queue, builds, and drains to completion with no
time limit. `--continue` releases leased items and drains without rebuilding.
Fine for a one-off from a terminal; prefer `sync:run` for anything scheduled.

## Settings

At `/admin/sync/settings`.

| Setting | Default | Meaning |
| --- | --- | --- |
| `email_fail` | — | Address notified when a run reports failures. The message includes the failure log |
| `log_verbose` | `false` | Write per-item log entries to watchdog. Leave off for large syncs |
| `lock_enabled` | `false` | Whether the Lock/Unlock UI is available. See [Locking an entity](#locking-an-entity) |
| `cron_build` | `true` | Whether cron starts syncs whose scheduled time has passed |
| `cron_queue` | `true` | Whether cron works through the queued records |
| `cron_queue_time` | `30` | Seconds cron may spend on each sync queue |

A sync happens in two stages, and the two booleans map onto them:

1. **Started** — the data is fetched and every record is queued (`cron_build`).
2. **Worked through** — the queue is processed a few records at a time until it
   is empty (`cron_queue`).

### Handing execution to an external scheduler

If you drive syncs with `drush sync:cron`, turn **both** cron settings off:

```yaml
cron_build: false
cron_queue: false
```

Otherwise core cron will build a competing run and claim items out from under
the runner. With `cron_queue` off, the queue definitions omit their `cron` key
entirely, so core skips them; the `/sync-cron/{key}` endpoint then runs
`sync:cron` after core cron so that path keeps working too.

Changing these rebuilds the queue worker definitions, which are cached.

## Permissions

| Permission | Grants |
| --- | --- |
| `access sync overview` | View `/admin/sync` and the logs |
| `manage sync` | Change settings (restricted) |
| `sync run all` | Run any resource |
| `sync run scheduled` | Run resources that have a cron schedule |
| `sync reset` | Reset a resource's last-run timestamp |
| `sync debug` | Use the debug output |
| `sync view all` | View all resources |
| `sync lock` | Lock and unlock synced entities (restricted) |

A `sync run <resource_id>` permission is also generated for every resource, so
access can be granted one resource at a time.

## Database schema

**`sync`** — maps a sync id to an entity.

| Column | Notes |
| --- | --- |
| `id` | The sync id. Primary key with `entity_type` |
| `entity_type` | Primary key with `id` |
| `entity_id` | The entity it produced |
| `locked` | When `1`, the entity is never updated or deleted by sync. See [Locking an entity](#locking-an-entity) |

**`sync_data`** — per-run bookkeeping, segmented by sync group.

| Column | Notes |
| --- | --- |
| `id` | The sync id. Primary key with `segment` |
| `segment` | The sync group, normally the resource id |
| `changed` | When the record was last seen. Drives cleanup |
| `hash` | Source fingerprint. `NULL` means unknown, which always processes |

## Locking an entity

Sometimes a synced entity is edited locally and those edits have to survive.
Locking it takes it out of sync's hands: the entity is never updated by a run,
and cleanup will never delete it, until it is unlocked again.

**Locking is off by default.** Switch on *Allow locking synced entities* at
`/admin/sync/settings` (`lock_enabled`) to expose it.

### From the UI

Once enabled, any entity sync tracks gains a **Lock from sync** operation in its
listing, for users with the *Lock and unlock synced entities* permission. The
operation becomes **Unlock from sync** once locked, and both go through a
confirmation step.

### From code

```php
$sync_storage = \Drupal::service('sync.storage');

// By entity, which covers every sync id pointing at it.
$sync_storage->setEntityLocked($entity);
$sync_storage->setEntityLocked($entity, FALSE);
$records = $sync_storage->loadByEntity($entity);

// Or by sync id and entity type.
$sync_storage->setLocked($id, 'node');
$sync_storage->isLocked($id, 'node');
```

### What a lock does

- `SyncResourceBase::accessEntity()` returns FALSE, so `doProcess()` throws a
  `SyncSkipException`, logs a warning naming the record, and counts a skip.
- `cleanupQueryAlter()` excludes locked records, so cleanup will not delete it
  even though the sync did not update it.
- The sync record's `changed` timestamp is still refreshed, so the entity does
  not drift into looking stale.
- The stored change-detection hash is **not** advanced while the record is
  locked. That matters: whatever changed at the source while the entity was
  locked is still seen as a change once it is unlocked, so unlocking picks the
  entity back up rather than leaving it permanently behind.
- Locking is per sync record, not per resource. If several resources write to
  the same entity, lock each one.

A lock persists until it is explicitly cleared. A routine sync writing a record
back leaves it untouched.

### Turning the setting back off

`lock_enabled` gates the **UI**, not enforcement. Any lock already set keeps
being honoured after the setting is switched off, so disabling it cannot quietly
start overwriting an entity someone deliberately protected. The trade-off is
that those locks then have no UI to clear them — the settings form warns when
you disable with locks still in place. Unlock first if you want them released,
or call `setEntityLocked($entity, FALSE)` from code, which works regardless of
the setting.

### Locking on a condition instead

For a rule rather than a manual decision — say, an entity flagged by an editor —
override `accessEntity()`:

```php
public function accessEntity(EntityInterface $entity) {
  if (!$entity->get('field_sync_locked')->isEmpty()) {
    return FALSE;
  }
  return parent::accessEntity($entity);
}
```

## Performance notes

In rough order of impact for a large sync:

1. **Enable change detection.** Skipping unchanged records avoids the entity
   load, the save, and the queue row. Nothing else comes close when most records
   are stable between runs.
2. **Do not rely on core cron for a large queue.** Core allocates each queue a
   fixed number of seconds per cron run, so throughput is capped no matter how
   often cron fires. Use `drush sync:run` or `drush sync:cron`, which drive the
   queue directly, and turn off `cron_queue`.
3. **Prefer a fetcher that pages natively.** Parser-emulated paging re-reads and
   re-parses the entire payload for each page.
4. **Keep per-item work out of the item.** A lookup that resolves to a small set
   of values is worth memoising rather than repeating per record.
5. **Leave `log_verbose` off.** One watchdog row per record is a lot of rows.
6. **Use `build_policy = "restart"` for snapshot sources**, so an unfinished run
   cannot accumulate behind the next one.

## Upgrading

Run database updates after every update; several releases add schema or config.

- **8005** adds `sync_data.hash`. Existing rows get `NULL`, which reads as
  "unknown" and processes normally, so the first run after the update behaves
  exactly as before.
- **8006** seeds `cron_queue`, `cron_build` and `cron_queue_time` with the
  values that reproduce the previously hardcoded behaviour.
- **8007** adds `lock_enabled`, off, so no site gains the locking UI without
  asking for it.

Change detection, `build_policy` and the new cron settings are all opt-in;
defaults preserve existing behaviour.

One behaviour change to be aware of: the `json` parser now throws on malformed
input instead of returning an empty array. Previously a corrupt payload looked
like a finished page, so the run would end early and report success.
