<!-- SPDX-License-Identifier: GPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: Netresearch DTT GmbH -->
# 1.0.7

## MISC

- a121608 Update dev tools
- e7734b1 DOC: remove hint about alpha state

## Contributors

- Rico Sonntag
- Sebastian Mendel

# 1.0.6

## MISC

- 4e39dac Fix extension configuration settings type
- fd334ef Fix phpstan-baseline command

## Contributors

- Rico Sonntag

# 1.0.5

## MISC

- fe4ae4a Update ext_emconf.php

## Contributors

- Rico Sonntag

# 1.0.4

## MISC

- 580d25c Fix extension configuration
- dcbc477 Update README and CI configuration

## Contributors

- Rico Sonntag

# 1.0.3

## MISC

- ab61081 Add CI test for PHP 81
- d5fe51a Apply php-cs-fixer rules
- 89d9bd0 Remove usage of vhs viewhelpers, fix various phpstan issues
- 06d2693 Use fully qualified reference of methods
- e99a415 Add a default area configuration to prevent exception on freshly installed versions
- 8305a10 Fix PHP 8.1 issues with already implemented PHP 8.2 features
- 6790e64 Add missing php ftp-extension dependency
- 2d56fde Add missing php zlib-extension dependency

## Contributors

- Rico Sonntag

# 1.0.2

## MISC

- 603bb10 Reorder modules to prevent that the first module loaded is a modules which only admins can access. This will lead to the beaviour that a user which is not an admin cant access the sync module at all even if he has permissons to it.

## Contributors

- Axel Seemann

# 1.0.1

## MISC

- 1b76223 Update rector configuration
- 9379f80 Update phpstan configuration

## Contributors

- Rico Sonntag

# 1.0.0

## MISC

- 6ff986f Apply codestyle fixes.
- 4297c0a Overtake encoding header fix to prevent problems with encoding in several cases.
- 453b005 Fixed the generation of sql files by consistently quoting all the identifiers. Also fix querys for ddeleting references to not delete references which should not be touched.
- 95db816 Fix TYPO3-issue #103388, set custom btn class
- 7acc978 Apply phpstan, rector, cgl rules
- 9b5c1a2 Add SyncImport scheduler task
- 3e56327 Add minor adjustments
- 990e973 Add event/eventlistener to trigger FAL sync
- c5a38c6 Rework extension
- 6cdcea0 Fix deprecated ViewInterface
- 9132a6d Fix backend module registration
- 85cc125 Update modules
- 20eed6c Remove obsolete AbstractService usage
- 6a9e5f5 Remove ObjectManger usage
- 1e71727 Update backend URI viewhelper
- 7c36359 TYPO3 v12 adjustments
- eaeff27 Require TYPO3 v12

## Contributors

- Axel Seemann
- Rico Sonntag

# 0.11.4

## MISC

- 26f8335 add sys_file as table which should synced with INSERT INTO REPLACE - avoid PRIMARY KEY 1 exists error message during sync, if some other files has been index in sys_file already

## Contributors

- Tobias.Hein

# 0.11.3

## MISC

- 553f107 Refactor empty check
- 0a7505d Skip insert mm delete lines for sys_file_references due to the table has a deleted marker ans do it's not neccessary anymore.
- 86e7927 Rework sync modules
- 084f737 Search only content elements which list_types begins with news. So we ensure only pages with news plugins are found.
- 4852c69 Fix .gitlab-ci.yml

## Contributors

- Axel Seemann
- Rico Sonntag
- Thomas Schöne

# 0.11.2

## MISC

- 62954ca Exclude zip archive from versioning
- 6fd2cfd Fix README
- 9c6a3ac Convert readme to markdown.
- 554f584 Use correct nr-sync backend mobule icon

## Contributors

- Axel Seemann
- Sebastian Koschel

# 0.11.1

## MISC

- a5478a3 Update readme.
- 71f6e25 Rename readme from .md to .rst

## Contributors

- Axel Seemann

# 0.11.0

## MISC

- 1eeb437 Create syncs for pages which contains news plugins.
- 7b351bb Remove sys_file_storage from fal sync. Due to we want to manage this for each environent separately.
- 4f54382 change accesslevel for textDB sync from 100 (admins only) to 50 - make it possible for non admins to sync textdb stuff
- 341ee67 Remove restrictions for determing page translations. So also disabled pages could be synced if necessary.
- 138e16d Make sync of redirects possible.
- 4b412e4 Add some common syncs
- f3cd98a Do not use deprecated methods
- a9e94c7 Determine Translations of a page on sync.,
- 3bf2628 rename signaling ftp user
- 16d9e58 Fix clearcache url.
- 54b9af0 Added missing extension-key in composer.json
- 1ee17a8 Updated logo
- 4b96d8e fix return type for getFunctionObject in Classes/Controller/SyncModuleController.php - avoid fatal error
- ad644b8 Fixed small bug with backend user
- 5b24577 More code cleanup, removed obsolete/duplicate methods, changed method visiblities, added use statements
- 067dd85 Removed obsolete sync entries
- b6658a9 Cleanup
- 07dc594 Cleanup
- 9853032 Refactored eID script to middleware
- aef15a8 Refactored CLI command
- 6c19335 More refactoring
- 24c7bc1 Moved HTML to templates
- e23abcc Refactoring
- e716bd0 Updated composer.json
- 7344186 Update .gitlab-ci.yml
- 811a5ac Update .gitlab-ci.yml
- 988e997 Update .gitlab-ci.yml
- 2103b34 .gitlab-ci.yml hinzufügen
- e007bbf use typo3 querybuilder instead of global db
- a77b07a add hook for content sync
- 56e748a add menu hook
- 621c72c add menu hook
- 28d6060 Version: 0.10.2
- afb6fa9 change notification target host.
- df6f088 Version: 0.10.1
- 5ba24cb Change process how to create clear Cache files
- d94d8d7 Version: 0.9.1
- 8b8eed2 Implement QueryBuilder method to return QueryBuilder without restrictions from enablefields in TCA.
- 9d4bff2 add service registration and eID call for clearcache service - make it possible to clear caches via URL curl in TYPO3 v8
- 7b5ee16 fix recursive single pages with content sync
- 4796692 refactor module functions into classes
- e734c9a add option to hide sync targets from stats and tools - hide never emptied archive sync target
- 731837f set proper file permission - allows deleting files by external sync job
- 6ee3327 fix context check
- 37397f6 fix sync button beeing disabled in wrong case
- 60ef365 tweak sync target lock button naming
- 52c70b1 tweak sync target stats display - reduce message box count
- 1335128 die not exit sync stats if one sync target system has no files - fixes not all sync target stats are displayed if one has no waiting files
- ecb1535 drop dead/superfluous code
- 76cf0da some translations
- 9e40ddb configure signal file behaviour by TYPO3_CONTEXT
- cf1fd25 set proper file permission - allows deleting files by external sync job
- f52e9db use real existing file as signal file source
- ca33530 be more verbose on FTP errors (signal files)
- af9610e confgiure and enable signal file targets
- 33be6a7 continue refactoring
- 2aecbab continue refactoring
- 3d7738e continue refactoring
- a6661ec continue refactoring
- 7a75cda Initial commit

## Contributors

- Axel Kummer
- Axel Seemann
- Rico Sonntag
- Sebastian Mendel
- Thomas Schöne
- Tobias.Hein

