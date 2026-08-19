<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Classes

<!-- AGENTS-GENERATED:START overview -->
## Overview
PHP source of the `nr_sync` backend extension (namespace `Netresearch\Sync\`, PSR-4 from `Classes/`). Sync-type-specific backend modules (assets, FAL, news, single page, table state) build SQL dump files and URL lists that target systems import; a scheduler task performs the import side.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `Controller/BaseSyncModuleController.php` | Shared base for all sync backend module controllers |
| `Table.php` | Controls table sync/dump generation |
| `SyncList.php` / `SyncListManager.php` | Manage the list of pages/tables selected for sync |
| `SyncLock.php` | Disables sync modules via extension configuration (`syncModuleLocked`) |
| `Generator/Urls.php` | Generates files with the URL lists target systems must call |
| `Middleware/ClearCache.php` | HTTP endpoint for clearing caches on target systems |
| `Scheduler/SyncImportTask/Task.php` | Scheduler task importing dump files on targets |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START golden-samples -->
## Golden Samples (follow these patterns)
| Pattern | Reference |
|---------|-----------|
| Backend module controller | `Controller/AssetSyncModuleController.php` |
| DI service | `Service/StorageService.php` |
| PSR-14 event | `Event/BeforeSyncEvent.php` |
| Shared behavior | `Traits/DumpFileTrait.php` |
<!-- AGENTS-GENERATED:END golden-samples -->

<!-- AGENTS-GENERATED:START setup -->
## Setup & environment
- Install dev tooling: `composer install` (binaries in `.build/bin/`, vendor in `.build/vendor/`)
- TYPO3 target: `^13.4` (see `../composer.json` / `../ext_emconf.php`)
- Runtime dependency: `netresearch/nr-scheduler`; PHP extensions `ext-ftp`, `ext-zlib`
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START structure -->
## Directory structure
```
Classes/
  Command/         → console commands (ClearCache)
  Controller/      → backend sync module controllers (Base/Asset/Fal/News/SinglePage/TableState)
  Event/           → PSR-14 events (BeforeSync, AfterSync, FalSync, ModifyMenuItems, ModifyTableList)
  EventListener/   → event listeners (FalSyncEventListener)
  Generator/       → URL list generation
  Helper/          → Area helper (contains @deprecated code)
  Middleware/      → PSR-15 middleware (clear-cache endpoint)
  Scheduler/       → SyncImportTask for target-side import
  Service/         → ClearCacheService, StorageService
  Traits/          → shared behavior (DumpFile, DatabaseConnection, FlashMessage, …)
  ViewHelpers/     → Fluid ViewHelpers (Backend, File, Folder, Format, Math)
```
Module registration lives in `../Configuration/Backend/Modules.php`, DI in `../Configuration/Services.yaml`, middleware in `../Configuration/RequestMiddlewares.php`.
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START commands -->
## Build & tests
| Task | Command |
|------|---------|
| PHP lint | `composer ci:test:php:lint` |
| PHPStan | `composer ci:test:php:phpstan` |
| Code style check | `composer ci:test:php:cgl` |
| Code style fix | `composer ci:cgl` |
| Rector dry-run | `composer ci:test:php:rector` |
| Unit tests | `composer ci:test:php:unit` |
| All checks | `composer ci:test` |
<!-- AGENTS-GENERATED:END commands -->

<!-- AGENTS-GENERATED:START code-style -->
## Code style & conventions
- **PSR-12** + TYPO3 CGL, enforced via `composer ci:test:php:cgl` (config `../Build/.php-cs-fixer.dist.php`)
- Strict types: `declare(strict_types=1);` in all PHP files
- Namespace: `Netresearch\Sync\` (PSR-4 from `Classes/`)
- Use constructor dependency injection via `../Configuration/Services.yaml`; avoid `GeneralUtility::makeInstance()` in new code
- Extend `BaseSyncModuleController` for new sync module controllers
- Emit/extend PSR-14 events in `Event/` for extensibility instead of hooks
- Never use raw SQL strings — use the Doctrine `QueryBuilder` (see `Traits/DatabaseConnectionTrait.php`)
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- This extension writes SQL dump files and touches multiple systems — treat all path and table input as untrusted
- **Always use QueryBuilder** with named parameters — never string-concatenated SQL
- **Escape output** in Fluid: `{variable}` auto-escapes, use `<f:format.raw>` only when safe
- **Access checks**: backend modules must respect BE user permissions (`$GLOBALS['BE_USER']`)
- Never log or dump credentials of target systems
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] `composer ci:test` passes (lint, phpstan, rector, cgl, unit)
- [ ] PHPStan clean without adding entries to `../Build/phpstan-baseline.neon`
- [ ] New events/services registered in `../Configuration/` where required
- [ ] `../ext_tables.sql` updated if DB fields change
- [ ] No deprecated TYPO3 APIs introduced
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> See **Golden Samples** section above for files that demonstrate correct patterns.
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- TYPO3 Documentation: https://docs.typo3.org
- Core API: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/
- Backend module API: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Backend/BackendModules.html
- Check existing sync module controllers for patterns
- Review root AGENTS.md for project-wide conventions; component map in `../docs/ARCHITECTURE.md`
<!-- AGENTS-GENERATED:END help -->

<!-- AGENTS-GENERATED:START skill-reference -->
## Skill Reference
> For TYPO3 extension standards, TER compliance, and conformance checks:
> **Invoke skill:** `typo3-conformance`
<!-- AGENTS-GENERATED:END skill-reference -->
