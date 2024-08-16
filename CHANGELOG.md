# 1.0.1

## MISC

- 1b76223 Update rector configuration
- 9379f80 Update phpstan configuration

## Contributors

- Rico Sonntag

# 1.0.0

## MISC

- 6ff986f MFAG-1516: Apply codestyle fixes.
- 4297c0a MFAG-1516: Overtake encoding header fix from OPSMFAG-676 to prevent problems with encoding in several cases.
- 453b005 MFAG-1516: Fixed the generation of sql files by consistently quoting all the identifiers. Also fix querys for ddeleting references to not delete references which should not be touched.
- 95db816 Fix TYPO3-issue #103388, set custom btn class
- 7acc978 Apply phpstan, rector, cgl rules
- 9b5c1a2 MFAG-1426: Add SyncImport scheduler task
- 3e56327 MFAG-1426: Add minor adjustments
- 990e973 MFAG-1426: Add event/eventlistener to trigger FAL sync
- c5a38c6 MFAG-1426: Rework extension
- 6cdcea0 MFAG-1426: Fix deprecated ViewInterface
- 9132a6d MFAG-1426: Fix backend module registration
- 85cc125 MFAG-1426: Update modules
- 20eed6c MFAG-1426: Remove obsolete AbstractService usage
- 6a9e5f5 MFAG-1426: Remove ObjectManger usage
- 1e71727 MFAG-1426: Update backend URI viewhelper
- 7c36359 MFAG-1426: TYPO3 v12 adjustments
- eaeff27 Require TYPO3 v12

## Contributors

- Axel Seemann
- Rico Sonntag

# 0.11.4

## MISC

- 26f8335 TYPO-8217: add sys_file as table which should synced with INSERT INTO REPLACE - avoid PRIMARY KEY 1 exists error message during sync, if some other files has been index in sys_file already

## Contributors

- Tobias.Hein

# 0.11.3

## MISC

- 553f107 TYPO-8199: Refactor empty check
- 0a7505d TYPO-8199: Skip insert mm delete lines for sys_file_references due to the table has a deleted marker ans do it's not neccessary anymore.
- 86e7927 MFI-173: Rework sync modules
- 084f737 MFI-152: Search only content elements which list_types begins with news. So we ensure only pages with news plugins are found.
- 4852c69 Fix .gitlab-ci.yml

## Contributors

- Axel Seemann
- Rico Sonntag
- Thomas Schöne

# 0.11.2

## MISC

- 62954ca TYPO-0: Exclude zip archive from versioning
- 6fd2cfd TYPO-0: Fix README
- 9c6a3ac TYPO-0: Convert readme to markdown.
- 554f584 ATU-153: Use correct nr-sync backend mobule icon

## Contributors

- Axel Seemann
- Sebastian Koschel

# 0.11.1

## MISC

- a5478a3 MFI-152: Update readme.
- 71f6e25 Rename readme from .md to .rst

## Contributors

- Axel Seemann

# 0.11.0

## MISC

- 1eeb437 MFI-152: Create syncs for pages which contains news plugins.
- 7b351bb TYPO-7717: Remove sys_file_storage from fal sync. Due to we want to manage this for each environent separately.
- 4f54382 TYPO-7643: change accesslevel for textDB sync from 100 (admins only) to 50 - make it possible for non admins to sync textdb stuff
- 341ee67 MFI-112: Remove restrictions for determing page translations. So also disabled pages could be synced if necessary.
- 138e16d MFI-112: Make sync of redirects possible.
- 4b412e4 MFI-112: Add some common syncs
- f3cd98a MFI-112: Do not use deprecated methods
- a9e94c7 MFI-112: Determine Translations of a page on sync.,
- 3bf2628 MFI-100: rename signaling ftp user
- 16d9e58 MFI-100: Fix clearcache url.
- 54b9af0 Added missing extension-key in composer.json
- 1ee17a8 TYPO-10: Updated logo
- 4b96d8e MFI-32: fix return type for getFunctionObject in Classes/Controller/SyncModuleController.php - avoid fatal error
- ad644b8 TYPO-10: Fixed small bug with backend user
- 5b24577 TYPO-10: More code cleanup, removed obsolete/duplicate methods, changed method visiblities, added use statements
- 067dd85 TYPO-10: Removed obsolete sync entries
- b6658a9 TYPO-10: Cleanup
- 07dc594 TYPO-10: Cleanup
- 9853032 TYPO-10: Refactored eID script to middleware
- aef15a8 TYPO-10: Refactored CLI command
- 6c19335 TYPO-10: More refactoring
- 24c7bc1 TYPO-10: Moved HTML to templates
- e23abcc TYPO-10: Refactoring
- e716bd0 TYPO-10: Updated composer.json
- 7344186 Update .gitlab-ci.yml
- 811a5ac Update .gitlab-ci.yml
- 988e997 Update .gitlab-ci.yml
- 2103b34 .gitlab-ci.yml hinzufügen
- e007bbf TYPO-7071: use typo3 querybuilder instead of global db
- a77b07a TYPO-7071: add hook for content sync
- 56e748a TYPO-7071: add menu hook
- 621c72c TYPO-7071: add menu hook
- 28d6060 Version: 0.10.2
- afb6fa9 TYPO-6856: change notification target. uzsync11 was replaced by uzsync12.
- df6f088 Version: 0.10.1
- 5ba24cb AR2-81: Change process how to create clear Cache files
- d94d8d7 Version: 0.9.1
- 8b8eed2 TYPO-6272: Implement QueryBuilder method to return QueryBuilder without restrictions from enablefields in TCA.
- 9d4bff2 AR2-81: add service registration and eID call for clearcache service - make it possible to clear caches via URL curl in TYPO3 v8
- 7b5ee16 AR2-55: fix recursive single pages with content sync
- 4796692 refactor module functions into classes
- e734c9a add option to hide sync targets from stats and tools - hide never emptied archive sync target
- 731837f AR2-49: set proper file permission - allows deleting files by external sync job
- 6ee3327 fix context check
- 37397f6 fix sync button beeing disabled in wrong case
- 60ef365 tweak sync target lock button naming
- 52c70b1 tweak sync target stats display - reduce message box count
- 1335128 die not exit sync stats if one sync target system has no files - fixes not all sync target stats are displayed if one has no waiting files
- ecb1535 drop dead/superfluous code
- 76cf0da some translations
- 9e40ddb configure signal file behaviour by TYPO3_CONTEXT
- cf1fd25 AR2-49: set proper file permission - allows deleting files by external sync job
- f52e9db use real existing file as signal file source
- ca33530 be more verbose on FTP errors (signal files)
- af9610e AR2-49: confgiure and enable signal file targets
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

