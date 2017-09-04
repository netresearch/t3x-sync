<?php
/**
 * Netresearch Sync
 *
 * PHP version 5
 *
 * @package  Netresearch/TYPO3/Sync
 * @author   Andre Hähnel <andre.haehnel@netresearch.de>
 * @license  https://www.gnu.org/licenses/agpl AGPL v3
 * @link     http://www.netresearch.de
 */

namespace Netresearch\Sync\Helper;
use Netresearch\Sync\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Helper methods
 *
 * @package  Netresearch/TYPO3/Sync
 * @author   Andre Hähnel <andre.haehnel@netresearch.de>
 * @license  https://www.gnu.org/licenses/agpl AGPL v3
 * @link     http://www.netresearch.de
 */
class Tablesync
{
    /**
     * Server configuration
     *
     * @var array $arServerConfig
     */
    protected static $arServerConfigs = array(
        'netresearch' => array(
            'dsn'  => 'mysql:dbname=aida_typo3;host=127.0.0.1',
            'user' => 'root',
            'pass' => 'EtsyuAkGecvi',
        ),
        'cmstest' => array(
            'dsn'  => 'mysql:dbname=typo46;host=urcms21.aida.de',
            'user' => 'typo3',
            'pass' => '4lEfmkl',
        ),
        'cmsprod' => array(
            'dsn'  => 'mysql:dbname=typo46;host=urcms11.aida.de',
            'user' => 'typo3',
            'pass' => '4lEfmkl',
        ),
        'live' => array(
            'dsn'  => 'mysql:dbname=typo46;host=192.168.3.122',
            'user' => 'typologs',
            'pass' => 'LogDb',
        ),
    );



    /**
     * Get Serverconfiguration for db connect
     *
     * @return array $arServerConfig
     * @throws Exception
     */
    protected static function getServerConfig()
    {
        if (isset($_SERVER['AIDA_ENV'])) {
            switch ($_SERVER['AIDA_ENV']) {
                case 'netresearch':
                    return self::$arServerConfigs['netresearch'];
                case 'cmstest':
                    return self::$arServerConfigs['cmsprod'];
                case 'cmsprod':
                    return self::$arServerConfigs['live'];
                default:
                    return self::$arServerConfigs['live'];
            }
        }

        throw new Exception('No ServerConfig available.');
    }



    /**
     * Make Dump from Live database Tables
     *
     * @param array $arTables tables for dump/sync
     *
     * @return array $arOutfile full file path from dumpfile and table name
     * @throws Exception
     * @TODO Das select sollte ein Limit bekommen, da es sonst bei großen
     *       Tabellen zu einem MemoryOverflow kommt.
     */
    protected static function makeDump(array $arTables = array())
    {
        $arServerConfig = self::getServerConfig();

        if (! is_dir(PATH_site . 'typo3temp/nr_sync')) {
            mkdir(PATH_site . 'typo3temp/nr_sync', 0777, true);
        }
        try {
            $db = new \PDO(
                $arServerConfig['dsn'],
                $arServerConfig['user'],
                $arServerConfig['pass']
            );
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }

        foreach ($arTables as $table) {

            $query = $db->query(
                "SELECT * FROM $table "
            );
            if ($query === false) {
                $arError = $db->errorInfo();
                throw new Exception(
                    $arError[0] . ': ' . $arError[1] . ': ' . $arError[2]
                );
            }

            $strOutfile = PATH_site . 'typo3temp/nr_sync/dump_'
                . $table . '_' . time() . '.txt';
            $strSql = "";
            $nLimit = 1000;
            $nLimitCounter = 0;
            $handle = fopen($strOutfile, "a");
            if (!$handle) {
                throw new Exception('Cannot open File.');
            }
            while ($data = $query->fetch(\PDO::FETCH_NUM)) {
                // insert
                $data = self::quoteArray($data, $db);
                $strSql .= implode(',', $data) . ';';
                $nLimitCounter++;
                if ($nLimit == $nLimitCounter) {
                    if (fwrite($handle, $strSql) !== false) {
                        $nLimitCounter = 0;
                        $strSql = '';
                    } else {
                        throw new Exception('Cannot write in File.');
                    }
                }
            }
            if (fwrite($handle, $strSql) === false) {
                throw new Exception('Cannot write in File.');
            }
            if (fclose($handle) === false) {
                throw new Exception('Cannot close File.');
            }

            $strTmp['file'] = $strOutfile;
            $strTmp['table'] = $table;
            $arOutfile[] = $strTmp;
        }

        return $arOutfile;
    }



    /**
     * Return array with quoted values
     *
     * @param array  $arData database result
     * @param \PDO   $db     connection
     *
     * @return array $arReturn include quoted string values
     */
    protected static function quoteArray(array $arData, \PDO $db)
    {
        $arReturn = array();
        foreach ($arData as $key => $value) {
            $arReturn[$key] = $db->quote($value);
        }
        return $arReturn;
    }



    /**
     * Import data from dumpfile into local database
     *
     * @param array $arDumpFile should be full file path to dumpfile and table name
     *
     * @return void
     * @throws Exception
     */
    protected static function importDump(array $arDumpFile = array())
    {
        /* @var $connectionPool \TYPO3\CMS\Core\Database\ConnectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        /* @var $connection \TYPO3\CMS\Core\Database\Connection */
        $connection = $connectionPool->getConnectionForTable('tx_nrsync_syncstat');

        if (count($arDumpFile) < 1) {
            throw new Exception('Dump file list is empty. Export did not create any dump files?');
        }

        foreach ($arDumpFile as $entry) {
            if (!is_file($entry['file'])) {
                throw new Exception('FILE :' . $entry['file'] . 'not exist');
            }
        }

        foreach ($arDumpFile as $entry) {
            $connection->truncate($entry['table']);

            $stmt = $connection->prepare(
                'LOAD DATA INFILE ' . $connection->quote($entry['file']) . '
                 INTO TABLE ' . $connection->quoteIdentifier($entry['table']) . '
                 FIELDS TERMINATED BY "," ENCLOSED BY "\'"
                 LINES TERMINATED BY ";" '
            );
            $stmt->execute();
        }

        foreach ($arDumpFile as $entry) {
            if (is_file($entry['file'])) {
                unlink($entry['file']);
            }
        }
    }



    /**
     * Sync tables from LIVE to local database.
     *
     * @param array  &$arParams hook parameters
     * @param Module $syncMod   calling object
     *
     * @see    postProcessSync()
     * @return boolean
     */
    public static function sync(array &$arParams, Module $syncMod)
    {
        // dump/export data from LIVE database
        try {
            $arDumpFile
                = Tablesync::makeDump($arParams['arTabellen']);
        } catch (Exception $e) {
            $syncMod->addError(
                $syncMod->strError . $e->getMessage()
            );
            $syncMod->addError(
                'Dump table from LIVE database failed.'
            );
            return false;
        }

        // import into local database
        try {
            Tablesync::importDump($arDumpFile);

            $syncMod->addSuccess(
                'Erfolgreich importiert: ' . implode(', ', $arParams['arTabellen'])
            );
        } catch (Exception $e) {
            $syncMod->addError(
                $syncMod->strError . $e->getMessage()
            );
            $syncMod->addError(
                'Import table into local database failed.'
            );
            return false;
        }

        return true;
    }
}
