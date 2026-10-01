<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# Security assurance

This document describes what users can and cannot expect from `nr_sync` in terms of security, its threat model and trust boundaries, the secure design principles it applies and how it counters common weaknesses. It covers the backend modules, the import task and the console command `sync:cache:clear`. The component map is in [ARCHITECTURE.md](ARCHITECTURE.md). Every statement refers to the code at the commit that contains this file. Vulnerabilities are reported as described in [SECURITY.md](../SECURITY.md) and the organisation's [security policy](https://github.com/netresearch/.github/blob/main/SECURITY.md).

Where a statement depends on TYPO3 itself, it refers to TYPO3 13.4.35, the version Composer resolved for `typo3/cms-core: ^13.4` on 2026-09-30 (the repository tracks no `composer.lock`).

## What the extension does and does not do

- On the **source system** (typically production), backend modules write SQL dump files and URL lists into the sync storage (`Classes/Traits/DumpFileTrait.php`, `Classes/Table.php`, `Classes/Generator/Urls.php`, `Classes/Service/StorageService.php`). The folders below the storage are the `directory` and `url-path` of each target system in the area configuration (`Classes/Helper/Area.php`).
- The extension does **not** transfer dump files to other hosts. Moving the files from the source system's sync storage to a target system is done outside the extension (README.md, "Description").
- On a **target system**, the scheduler task `Classes/Scheduler/SyncImportTask/Task.php` imports the dump files it finds in its configured folder with the `mysql` client and processes the URL lists by clearing the listed caches.
- Apart from the database connections of TYPO3 and of the `mysqldump` and `mysql` clients, the only network connection the extension opens is the optional FTP notification of a target system (`Area::notifyMasterViaFtp()`), which uploads two empty trigger files, `db.txt` and `files.txt`.

## Actors

| Actor | What it can do | How it reaches the extension |
|-------|----------------|------------------------------|
| Backend administrator | Uses every sync module, locks and unlocks the whole sync module, configures the import task in the Scheduler module | TYPO3 backend |
| Backend editor with module access | Uses the sync modules registered with `'access' => 'user'` (single page, FAL, frontend groups, redirects, news) once an administrator has granted the module to the user or a group | TYPO3 backend |
| Integrator | Writes `config/system/sync-area-configuration.php` (composer installations) or `typo3conf/system/sync-area-configuration.php` (`Area::getAreaConfigurationFileLocation()`), which defines areas, target systems and FTP notification credentials | File system of the source system |
| Operator | Moves dump files to the target systems, runs the TYPO3 scheduler and the console command `sync:cache:clear` | Host and TYPO3 CLI |
| Extension developer | Adds or removes tables through the PSR-14 events `BeforeSyncEvent` and `ModifyTableListEvent` | PHP code in an installed extension |

## Data that leaves the instance

Each module writes the rows of a fixed list of tables, set in `Configuration/Backend/Modules.php`:

| Module | Access | Tables written to the dump |
|--------|--------|---------------------------|
| Single page (`netresearch_sync_singlePage`) | user | `pages`, `sys_file_reference`, `sys_template`, `tt_content`, limited to the selected pages and their records |
| FAL (`netresearch_sync_fal`) | user | `sys_category`, `sys_category_record_mm`, `sys_file`, `sys_file_metadata`, `sys_file_reference`, `sys_filemounts` |
| Frontend groups (`netresearch_sync_fe_groups`) | user | `fe_groups` |
| Redirects (`netresearch_sync_redirect`) | user | `sys_redirect` |
| News (`netresearch_sync_news`, only when `georgringer/news` is loaded) | user | `sys_category`, `sys_category_record_mm`, `sys_file_reference` and the `tx_news_domain_model_*` tables listed in the file |
| Backend users (`netresearch_sync_be_users`) | admin | `be_groups`, `be_users`, including the stored password hashes |
| Scheduler (`netresearch_sync_scheduler`) | admin | `tx_scheduler_task`, `tx_scheduler_task_group` |
| Assets (`netresearch_sync_asset`) | admin | No dump; the module only triggers the notification of the target systems (`AssetSyncModuleController::run()`) |
| Table state (`netresearch_sync_tableState`) | admin | No rows; the names of all database tables and their columns go into a state file in the sync storage (`TableStateSyncModuleController::createNewDefinitions()`) |

Listeners of `BeforeSyncEvent` and `ModifyTableListEvent` can change these lists (`BaseSyncModuleController::getTables()`, `DumpFileTrait::createDumpToAreas()`).

The dumps contain complete rows. They are gzip-compressed (`DumpFileTrait::createGZipFile()`) but neither encrypted nor signed. The URL lists contain the cache identifiers to clear (`table:uid` entries), not content.

## What users can expect

- **Module access follows TYPO3's backend permissions.** The modules that write backend users and scheduler tasks, and the asset and table-state modules, are registered with `'access' => 'admin'`; the others with `'access' => 'user'`, which TYPO3 shows only to administrators and to users or groups an administrator has granted the module (`Configuration/Backend/Modules.php`).
- **Page-based syncs respect page permissions.** When the pages of a sync list are collected, a page is included only if the backend user has the `PAGE_EDIT` permission on it (`SyncList::getAllPageIDs()`, `Classes/SyncList.php` lines with `doesUserHaveAccess($pageRow, Permission::PAGE_EDIT)`).
- **Only administrators can change the module lock.** The lock request is evaluated only when `isAdmin()` is true (`BaseSyncModuleController::getModuleTemplate()`, `SyncLock::handleModuleLock()`). The lock is stored in the extension configuration (`ext_conf_template.txt`: `syncModuleLocked`) and, while it is set, `BaseSyncModuleController::initModule()` returns before it prepares the module view.
- **A sync does not overwrite an unfinished one.** A dump is not started while a temporary or target file of the same name exists (`DumpFileTrait::openTempDumpFile()`, `DumpFileTrait::createDumpToAreas()`).
- **SQL written into dumps is quoted or numeric.** In the page-based dumps, identifiers go through Doctrine DBAL's `quoteSingleIdentifier()` and row values through `quote()`, except values that PHP's `is_numeric()` accepts, which are written unquoted as numeric literals (so a numeric-looking string such as `007` is imported as a number, not as text); DELETE statements take the uid as an integer (`DumpFileTrait::buildInsertUpdateLine()`, `DumpFileTrait::buildDeleteLine()`). Full and incremental table dumps are produced by `mysqldump` (`Table::appendDumpToFile()`, `Table::appendUpdateToFile()`).

## What users cannot expect

- **No confidentiality or integrity of the dumps.** The extension does not encrypt, sign or access-protect the dump files and URL lists. Protecting the sync storage on the source system, the transfer to the target systems and the import folder there is the operator's task.
- **No protected transport.** The extension transfers no dumps. The optional FTP notification uses plain FTP (`ftp_connect()`, not `ftp_ssl_connect()`), so its user name and password travel unencrypted; use it only on a network you trust or leave `notify.type` at `none` (the default in `Configuration/DefaultAreaConfiguration.php`).
- **No validation of dump content on import.** The import task executes every `.gz` file in its configured folder as SQL with the credentials of TYPO3's default database connection (`Task::importSqlFiles()`). Whoever can write to that folder controls the target database.
- **No filtering of sensitive columns.** Rows are dumped as stored, including the password hashes in `be_users`.

## Deployment requirements

- Keep the area configuration file outside the public web directory. With a composer installation, `config/system/` is outside it.
- Make sure the sync storage on the source system and the import folder on the target system are not reachable over HTTP and are writable only by TYPO3 and the transfer process. Do not grant backend file mounts on these folders to users who must not change the target database.
- Grant the `user` modules only to editors who may publish the listed tables to every target system of the area.
- The source and target hosts need the `mysqldump` and `mysql` clients on the `PATH` of the PHP process.

## Threat model and trust boundaries

| Boundary | Input | Treatment |
|----------|-------|-----------|
| Backend user → sync module | POST fields `data[...]`, `target`, page id | TYPO3 authenticates the user and enforces module access before the controller runs. The page id is cast to `int` (`BaseSyncModuleController::getPageId()`). `target` selects the target systems by comparison with the keys of the area configuration (`Area::removeUnwantedSystems()`, `Area::getSystem()`); lower-cased, it also becomes part of the dump file name (`BaseSyncModuleController::addInformationToSyncfileName()`), which TYPO3's local storage driver sanitizes when it creates or copies the file (`LocalDriver::sanitizeFileName()` replaces unsafe characters, including `/`, with `_`). Page selection is filtered by `PAGE_EDIT` (see above). `data[force_full_sync]` and `data[delete_obsolete_rows]` are compared with the string `'1'` (`DumpFileTrait::createDumpToAreas()`) |
| Integrator → area configuration | PHP file returning an array | Trusted: the file is `require`d (`Area::getAreaConfiguration()`); whoever can write it can run PHP on the source system |
| Extension code → table list | PSR-14 listeners | Trusted: listeners run with the privileges of TYPO3 |
| TYPO3 configuration → shell commands | Database host, user, password and name from TYPO3's connection parameters; table names from the module configuration and the listeners; for incremental dumps the condition `<TCA tstamp field> > <integer from tx_nrsync_syncstat>` (`Table::getDumpWhereCondition()`) | The `mysqldump` command line (`Table.php`) and the `mysql` command line (`Task.php`) are built from these trusted sources only. No request parameter reaches them. The file passed to `mysql` is created by `tempnam()` (`Task::importSqlFiles()`) |
| Sync storage → import task | `.gz` files and `*once.txt` URL lists in the folders configured for the task | Trusted, see "What users cannot expect". The task reads only files with the extension `gz` (`Task::findFilesToImport()`) and only lists whose name ends in `once.txt` (`Task::findUrlFiles()`); from a list it takes only entries matching `[a-zA-Z]+:[0-9|a-zA-Z\-_]+` (`Task::clearCaches()`) |
| Operator → `sync:cache:clear` | `--data` or `--filename` | Trusted: whoever runs the TYPO3 CLI has the privileges of TYPO3 (`Classes/Command/ClearCache.php`) |

## Secure design principles applied

- **Least privilege:** the modules that write backend users and scheduler tasks are admin-only; the other modules must be granted explicitly (`Configuration/Backend/Modules.php`).
- **Complete mediation of page access:** every page of a sync list is checked against `PAGE_EDIT` when the dump is built, not only when the page is added (`SyncList::getAllPageIDs()`).
- **Fail-safe defaults:** the FTP notification is off unless `notify.type` is `ftp` and the current application context matches `notify.contexts` (`Area::systemIsNotifyEnabled()`); the default area configuration sets `type` to `none`.
- **Economy of mechanism:** the extension does not implement its own transfer, encryption or authentication; it relies on TYPO3's backend authentication for its backend modules and leaves transport to the operator's tooling.

## Countering common weaknesses

| Weakness (CWE / OWASP) | Counter | Evidence |
|------------------------|---------|----------|
| CWE-89 SQL injection (A03) | Queries against TYPO3's database use the Doctrine QueryBuilder or quoted values; the conditions built as strings contain only TCA field names, quoted identifiers and integers; values written into dumps are quoted or, if `is_numeric()` accepts them, written as numeric literals | `DumpFileTrait::buildInsertUpdateLine()`, `DumpFileTrait::buildDeleteLine()`, `Table::getDumpWhereCondition()`, `Table::getSqlDroppingObsoleteRows()`, `Table::setLastDumpTime()` |
| CWE-78 OS command injection (A03) | The command lines take no request data; their inputs are TYPO3's own database configuration, configured table names and integers | `Table::appendDumpToFile()`, `Table::appendUpdateToFile()`, `Task::importSqlFiles()` |
| CWE-22 path traversal, CWE-377 insecure temporary file | Files are created through TYPO3's FAL API in fixed folders; the import writes the decompressed dump to a file created by `tempnam()` and deletes it afterwards | `StorageService`, `Task::importSqlFiles()` |
| CWE-862 missing authorisation (A01) | In the backend modules: module access through TYPO3's module permissions; page access through `PAGE_EDIT`; changing the module lock only for administrators | `Configuration/Backend/Modules.php`, `SyncList::getAllPageIDs()`, `BaseSyncModuleController::getModuleTemplate()` |
| CWE-79 cross-site scripting (A03) | Backend views are Fluid templates, which escape variables by default. Two places pass markup through unescaped: `WaitList.html` outputs the table it rendered before with `f:format.raw()`, and `FlashMessageViewHelper` does not escape its children, which in `WaitList.html` are a translated message with a file count, a size, a date and a number of minutes | `Resources/Private/Templates/`, `Resources/Private/Partials/`, `Classes/ViewHelpers/FlashMessageViewHelper.php` |
| Vulnerable dependencies (A06) | Composer Audit, Dependency Review and Renovate, see README.md, "Governance and policies" | `.github/workflows/checks.yml`, `renovate.json` |

## Verification

The unit tests in `Tests/Unit/` cover the PSR-14 events and the module group icon; no test covers the controls listed above. Static analysis (PHPStan level 6 with a baseline, Opengrep, CodeQL for the workflows) and secret scanning run on every pull request, as listed in README.md, "Governance and policies".
