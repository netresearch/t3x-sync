# Architecture

Agent-facing component map of `netresearch/nr-sync` (TYPO3 extension key `nr_sync`). Every path below is verified against the tree; update this file when components move.

## System overview

The extension prepares content of a TYPO3 source system (typically production) for synchronization to one or more target systems. Editors use sync-type-specific backend modules to select pages/tables; the extension writes SQL dump files and URL lists. It does **not** transfer them itself — target systems import the dumps via a scheduler task and get their caches cleared through an HTTP middleware endpoint.

## Components

| Component | Path | Responsibility |
|-----------|------|----------------|
| Backend module controllers | `Classes/Controller/` | One controller per sync type (Asset, FAL, News, SinglePage, TableState), all extending `BaseSyncModuleController.php`; registered in `Configuration/Backend/Modules.php` |
| Sync list | `Classes/SyncList.php`, `Classes/SyncListManager.php` | Track pages/tables selected for synchronization |
| Dump generation | `Classes/Table.php`, `Classes/Traits/DumpFileTrait.php` | Control table sync and SQL dump file creation |
| URL list generation | `Classes/Generator/Urls.php` | Write files with URLs target systems must call |
| Sync lock | `Classes/SyncLock.php` | Disable sync modules via extension configuration (`ext_conf_template.txt`: `syncModuleLocked`) |
| Sync statistics | `Classes/SyncStats.php`, `ext_tables.sql` | Per-table sync state in `tx_nrsync_syncstat` |
| Import task | `Classes/Scheduler/SyncImportTask/` | Scheduler task (extends `netresearch/nr-scheduler` `AbstractTask`) importing the sync MySQL files on targets; registered in `ext_localconf.php` |
| Cache clearing | `Classes/Service/ClearCacheService.php`, `Classes/Middleware/ClearCache.php`, `Classes/Command/ClearCache.php` | Clear caches for synced tables: service, frontend middleware endpoint (`Configuration/RequestMiddlewares.php`), console command `sync:cache:clear` |
| Extensibility | `Classes/Event/`, `Classes/EventListener/` | PSR-14 events (BeforeSync, AfterSync, FalSync, ModifyMenuItems, ModifyTableList) documented in `README.md` |
| Storage | `Classes/Service/StorageService.php`, `Classes/Helper/Area.php`, `Configuration/DefaultAreaConfiguration.php` | Resolve sync storage/areas and target directories (`Area` contains `@deprecated` code) |
| View layer | `Classes/ViewHelpers/`, `Resources/Private/` | Fluid ViewHelpers, templates, partials for the backend modules |
| DI configuration | `Configuration/Services.yaml` | Autowiring; backend controllers tagged `backend.controller`, FalSync listener tagged `event.listener` |

## Data flow

1. Editor opens a sync backend module (source system); `SyncLock` may block usage via extension configuration.
2. Controller builds the sync list (`SyncList`/`SyncListManager`), dispatches `BeforeSyncEvent`, and writes SQL dump files (`Table`, `DumpFileTrait`) plus URL lists (`Generator/Urls`) into the sync storage.
3. Sync state per table is recorded in `tx_nrsync_syncstat` (`SyncStats`); `AfterSyncEvent` is dispatched.
4. On each target system, the `SyncImportTask` scheduler task imports the dump files and triggers cache clearing (`ClearCacheService`), also reachable via the `nr/nr-sync/clear-cache` middleware and the `sync:cache:clear` command.

## Key decisions

- No ADR directory exists. Behavioral contracts live in: `README.md` (PSR-14 event usage), `.github/workflows/checks.yml` (gate/required-check rationale, in-file comments), and `AGENTS.md` files (working conventions).
- There is no `Tests/Architecture/` (phpat) suite; dependency rules are not machine-enforced.
