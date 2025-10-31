[![Latest version](https://img.shields.io/github/v/release/netresearch/t3x-sync?sort=semver)](https://github.com/netresearch/t3x-sync/releases/latest)
[![License](https://img.shields.io/github/license/netresearch/t3x-sync)](https://github.com/netresearch/t3x-sync/blob/main/LICENSE)
[![CI](https://github.com/netresearch/t3x-sync/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/t3x-sync/actions/workflows/ci.yml)
[![Crowdin](https://badges.crowdin.net/typo3-extension-nr-sync/localized.svg)](https://crowdin.com/project/typo3-extension-nr-sync)

# nr-sync - TYPO3 Content Synchronization

## Introduction

* Prepares your Content for a synchronization wherever you want
* Easy integration for your own extensions
* No content editing on live systems anymore

## Description

![Workflow](Documentation/Images/SyncWorkflow.png)

The extension provides an easy and editor friendly way to prepare the content for a synchronization to other
environments e.g. live, testing or development systems. All the synchronizations can be done complete or
incremental to keep the required load to an absolute minimum. The extension won't do the synchronization by itself.


## Installation

### Composer
``composer require netresearch/nr-sync``

### GIT
``git clone git@github.com:netresearch/t3x-sync.git``


## PSR-14 Events

The extension provides several PSR-14 events that allow you to extend and customize the synchronization behavior:

### BeforeSyncEvent

Dispatched before a sync dump is created. Allows you to modify the tables that will be synced.

**Event Class:** `Netresearch\Sync\Event\BeforeSyncEvent`

**Example Usage:**
```php
<?php
namespace Vendor\MyExtension\EventListener;

use Netresearch\Sync\Event\BeforeSyncEvent;

final class ModifyTablesBeforeSync
{
    public function __invoke(BeforeSyncEvent $event): void
    {
        $tables = $event->getTables();
        // Add a custom table to the sync
        $tables[] = 'tx_myextension_domain_model_item';
        $event->setTables($tables);
    }
}
```

**Register in `Configuration/Services.yaml`:**
```yaml
Vendor\MyExtension\EventListener\ModifyTablesBeforeSync:
    tags:
        - name: event.listener
          event: Netresearch\Sync\Event\BeforeSyncEvent
```

### AfterSyncEvent

Dispatched after a sync dump has been created. Allows you to perform actions after the sync completes.

**Event Class:** `Netresearch\Sync\Event\AfterSyncEvent`

**Example Usage:**
```php
<?php
namespace Vendor\MyExtension\EventListener;

use Netresearch\Sync\Event\AfterSyncEvent;

final class LogAfterSync
{
    public function __invoke(AfterSyncEvent $event): void
    {
        if ($event->isSuccess()) {
            // Log successful sync
            $logger->info('Sync completed successfully', [
                'dumpFile' => $event->getDumpFile(),
                'tables' => $event->getTables(),
            ]);
        }
    }
}
```

**Register in `Configuration/Services.yaml`:**
```yaml
Vendor\MyExtension\EventListener\LogAfterSync:
    tags:
        - name: event.listener
          event: Netresearch\Sync\Event\AfterSyncEvent
```

### ModifyMenuItemsEvent

Dispatched when building the sync module menu. Allows you to add custom menu items to the backend module.

**Event Class:** `Netresearch\Sync\Event\ModifyMenuItemsEvent`

**Example Usage:**
```php
<?php
namespace Vendor\MyExtension\EventListener;

use Netresearch\Sync\Event\ModifyMenuItemsEvent;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;

final class AddCustomMenuItem
{
    public function __invoke(ModifyMenuItemsEvent $event): void
    {
        $menuItem = GeneralUtility::makeInstance(MenuItem::class);
        $menuItem->setTitle('Custom Sync');
        $menuItem->setHref('/custom-sync-url');
        $menuItem->setActive(false);
        
        $event->addMenuItem($menuItem);
    }
}
```

**Register in `Configuration/Services.yaml`:**
```yaml
Vendor\MyExtension\EventListener\AddCustomMenuItem:
    tags:
        - name: event.listener
          event: Netresearch\Sync\Event\ModifyMenuItemsEvent
```

### ModifyTableListEvent

Dispatched when determining which tables to sync for a module. Allows you to dynamically add or remove tables.

**Event Class:** `Netresearch\Sync\Event\ModifyTableListEvent`

**Example Usage:**
```php
<?php
namespace Vendor\MyExtension\EventListener;

use Netresearch\Sync\Event\ModifyTableListEvent;

final class AddCustomTables
{
    public function __invoke(ModifyTableListEvent $event): void
    {
        // Add a table only for specific modules
        if ($event->getModuleIdentifier() === 'netresearch_sync_singlePage') {
            $event->addTable('tx_myextension_domain_model_item');
        }
        
        // Or remove a table
        $event->removeTable('unwanted_table');
    }
}
```

**Register in `Configuration/Services.yaml`:**
```yaml
Vendor\MyExtension\EventListener\AddCustomTables:
    tags:
        - name: event.listener
          event: Netresearch\Sync\Event\ModifyTableListEvent
```

### FalSyncEvent

Dispatched to immediately trigger a FAL (File Abstraction Layer) sync.

**Event Class:** `Netresearch\Sync\Event\FalSyncEvent`

**Example Usage:**
```php
<?php
namespace Vendor\MyExtension\Service;

use Netresearch\Sync\Event\FalSyncEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

final class MyService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function triggerFalSync(): void
    {
        $event = new FalSyncEvent(
            areaId: 1,
            dumpFilePrefix: 'my-custom-prefix'
        );
        $this->eventDispatcher->dispatch($event);
    }
}
```


## Development
### Testing
```bash
composer install

composer ci:cgl
composer ci:test
composer ci:test:php:phplint
composer ci:test:php:phpstan
composer ci:test:php:rector
```
