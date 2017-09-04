*********
nr_sync
*********

.. contents:: Inhaltsverzeichnis

=========
Übersicht
=========

Produktbeschreibung
===================
Sync content from production system to LIVE frontend servers.


==========================
Ablauf eines Syncprozesses
==========================

1. Im TYPO3-Backend wird z.B. eine Seite gesynct.
   It's possible to avoid a full sync and execute an incremental sync. So, only
   elements newer or changed since last sync will be added to the new sync.
   Das TYPO3 exportiert die Seite in eine .sql-Datei (``partly-pages_*.sql.gz``)
   Zusätzlich wird eine ``db.txt`` im Document Root angelegt, damit
   das Syncscript seitens Boreus darauf aufmerksam wird, dass etwas
   zu syncen ist. Desweiteren kann eine Datei angelegt werden, die Informationen
   enthält, welche caches gelöscht werden sollen. Diese Datei enthält Komma-
   oder Zeilensepariert die Einträge zu Tabelle und uid (getrennt durch den
   Doppelpunkt, wie in TYPO3 üblich.) Beispiel: "pages:18100". Die Datei lautet
   dann ``partly-pages_*.cache`` (mit gleichem Zeitstempel).
2. Alle 10 Minuten schaut ein Script bei Boreus nach der ``db.txt``
   und holt bei Vorhandensein die Dateien aus ``db/sync-live/``
   auf den Liveserver und spielt sie ein.
   Wird eine ``.cache`` Datei dazu gefunden, wird sie an das cli als Parameter
   übergeben. (Siehe dazu auch http://jira.aida.de/jira/browse/SDM-7896)
   Das Cli löscht dann die caches der angegebenen Tabellen und deren Objekte.

   Änderungen im ``fileadmin/`` werden automatisch alle 5 Minuten synchronisiert.

3. Es ist möglich Daten von Live nach Prod zu synchronisieren.
   -- wenn die Variable ``strTableType = 'backsync_tables'`` ist

4. URLs auf dem Zielsystem werden von Boreus aufgerufen.

   Dazu gibt es den Order ``db/urls/*/``, in dem zwei Typen von Dateien liegen:

   - ``....-per-machine.txt`` mit URLs, die auf jeder Maschine des Verbundes
     aufgerufen werden müssen
   - ``....-once.txt`` mit URLs, die nur einmal aufgerufen werden müssen.

   Mit diesen URLs ist es möglich, hook-mäßig Aktionen auf dem Zielsystem
   auszuführen - wie z.B. die Regenerierung der Webservicekonfigurationen.


=====
Hooks
=====

``postProcessMenu``

    Hook to post process the mod menu.
    Used to add mod menu entry


``preProcessSync``

    Hook to pre process variables controlling the sync process.
    Used to configure the sync process to include tables, pages
    or or whatever the sync tool supports.


``postProcessSync``

    Hook called after sync process finished.
    Used to run additional processing or do other stuff required for syncing.


Register a hook class::

    $TYPO3_CONF_VARS['SC_OPTIONS']['nr_sync/mod1/index.php']
        ['hookClass'][$_EXTKEY] = 'Aida_Dyncat2_Sync';


===================
Seiten-Cache leeren
===================

Netresearch Sync stellt eine API bereit um den Seiten-Cache einer Seite zu leeren.

Dies geschieht über eine TYPO3 eID: ``'nr_sync'``


Parameter
=========

task - always 'clearCache'::

 task=clearCache

data - comma seperated list of table:uid pairs::

 data=pages:123,pages:124


Example
=======

Clear page cache for pages 123 and 124::

  http://example.org/?eID=nr_sync&task=clearCache&data=pages:123,pages:124
  http://example.org/?eID=nr_sync;task=clearCache;data=pages:123,pages:124


=====
Setup
=====

report_error
============
* Parameter ``report_error`` could be ``true`` or ``false``
* If true, the errors when sending the signal file to FTP server would be reported
* If fals, the errors when sending the singal file to FTP server will be supressed

example::

         'system'               => array(
         // enable error logging for this environment
            'LIVE' => array(
                'directory' => 'sync-live',
                'notify'    => array(
                    'type'     => 'ftp',
                    'host'     => 'www.example.org',
                    'user'     => 'user',
                    'password'  => 'password',
                ),
                'report_error' => true,
            ),
            // disable error logging for this environment
            'TEST' => array(
                'directory' => 'sync-test',
                'notify'    => array(
                    'type'     => 'ftp',
                    'host'     => 'www.example.org',
                    'user'     => 'user',
                    'password'  => 'password',
                ),
                'report_error' => false,
            )

================
Lock Sync-Module
================

The Sync module can be locked by Admins. Just select the "Lock Netresearch Sync Module" Option in the module.

======================
File Lock Clean Script
======================
Sometime lock files created by nr_sync remains on the system even if the sync
file creation has been finished successfully e.g. cause a php process crashed.

The simple script ``scripts/clean-lock.sh`` checks for files in ``db/tmp`` older
than 5 minutes and removes them. You can add this script e.g. to your crontab::

   */5 * * * * /usr/bin/sh /path/to/typo3/typo3conf/ext/nr_sync/scripts/clean-lock.sh

