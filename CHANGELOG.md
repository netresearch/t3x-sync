# 0.11.1

## MISC

- MFI-152: Update readme. a5478a3
- Rename readme from .md to .rst 71f6e25

# 0.11.0

## MISC

- MFI-152: Create syncs for pages which contains news plugins. 1eeb437
- TYPO-7717: Remove sys_file_storage from fal sync. Due to we want to manage this for each environent separately. 7b351bb
- TYPO-7643: change accesslevel for textDB sync from 100 (admins only) to 50 - make it possible for non admins to sync textdb stuff 4f54382
- MFI-112: Remove restrictions for determing page translations. So also disabled pages could be synced if necessary. 341ee67
- MFI-112: Make sync of redirects possible. 138e16d
- MFI-112: Add some common syncs 4b412e4
- MFI-112: Do not use deprecated methods f3cd98a
- MFI-112: Determine Translations of a page on sync., a9e94c7
- MFI-100: rename signaling ftp user 3bf2628
- MFI-100: Fix clearcache url. 16d9e58
- Added missing extension-key in composer.json 54b9af0
- TYPO-10: Updated logo 1ee17a8
- MFI-32: fix return type for getFunctionObject in Classes/Controller/SyncModuleController.php - avoid fatal error 4b96d8e
- TYPO-10: Fixed small bug with backend user ad644b8
- TYPO-10: More code cleanup, removed obsolete/duplicate methods, changed method visiblities, added use statements 5b24577
- TYPO-10: Removed obsolete sync entries 067dd85
- TYPO-10: Cleanup b6658a9
- TYPO-10: Cleanup 07dc594
- TYPO-10: Refactored eID script to middleware 9853032
- TYPO-10: Refactored CLI command aef15a8
- TYPO-10: More refactoring 6c19335
- TYPO-10: Moved HTML to templates 24c7bc1
- TYPO-10: Refactoring e23abcc
- TYPO-10: Updated composer.json e716bd0
- Update .gitlab-ci.yml 7344186
- Update .gitlab-ci.yml 811a5ac
- Update .gitlab-ci.yml 988e997
- .gitlab-ci.yml hinzufügen 2103b34
- TYPO-7071: use typo3 querybuilder instead of global db e007bbf
- TYPO-7071: add hook for content sync a77b07a
- TYPO-7071: add menu hook 56e748a
- TYPO-7071: add menu hook 621c72c
- Version: 0.10.2 28d6060
- TYPO-6856: change notification target. uzsync11 was replaced by uzsync12. afb6fa9
- Version: 0.10.1 df6f088
- AR2-81: Change process how to create clear Cache files 5ba24cb
- Version: 0.9.1 d94d8d7
- TYPO-6272: Implement QueryBuilder method to return QueryBuilder without restrictions from enablefields in TCA. 8b8eed2
- AR2-81: add service registration and eID call for clearcache service - make it possible to clear caches via URL curl in TYPO3 v8 9d4bff2
- AR2-55: fix recursive single pages with content sync 7b5ee16
- refactor module functions into classes 4796692
- add option to hide sync targets from stats and tools - hide never emptied archive sync target e734c9a
- AR2-49: set proper file permission - allows deleting files by external sync job 731837f
- fix context check 6ee3327
- fix sync button beeing disabled in wrong case 37397f6
- tweak sync target lock button naming 60ef365
- tweak sync target stats display - reduce message box count 52c70b1
- die not exit sync stats if one sync target system has no files - fixes not all sync target stats are displayed if one has no waiting files 1335128
- drop dead/superfluous code ecb1535
- some translations 76cf0da
- configure signal file behaviour by TYPO3_CONTEXT 9e40ddb
- AR2-49: set proper file permission - allows deleting files by external sync job cf1fd25
- use real existing file as signal file source f52e9db
- be more verbose on FTP errors (signal files) ca33530
- AR2-49: confgiure and enable signal file targets af9610e
- continue refactoring 33be6a7
- continue refactoring 2aecbab
- continue refactoring 3d7738e
- continue refactoring a6661ec
- Initial commit 7a75cda

