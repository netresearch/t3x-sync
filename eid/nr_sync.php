<?php

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * eID Robot
 *
 * PHP version 5
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Alexander Opitz <alexander.opitz@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */

use Netresearch\Sync\Service\ClearCache;
use TYPO3\CMS\Core\Utility\GeneralUtility,
    TYPO3\CMS\Frontend\Utility\EidUtility;

/**
 * eID interface for nr_sync
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Alexander Opitz <alexander.opitz@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */
class Nr_Sync_Eid
{
    /**
     * @var string Same as class name
     */
    public $prefixId      = 'Nr_Sync_Eid';

    /**
     * @var string Path to this script relative to the extension dir.
     */
    public $scriptRelPath = 'eid/nr_sync.php';

    /**
     * @var string The extension key.
     */
    public $extKey        = 'nr_sync';

    /**
     * @var int HTTP return code: All ok.
     */
    public const HTTP_OK            = 200;

    /**
     * @var int HTTP return code: Error Bad Request.
     */
    public const HTTP_ERROR_COMMAND = 400;

    /**
     * @var int HTTP return code: Unknown.
     */
    public const HTTP_ERROR_UNKNOWN = 500;

    /**
     * @var clearCache Service object to call.
     */
    private $service;



    /**
     * Main function with echo output.
     *
     * @return void
     */
    public function main(): void
    {
        // header will be set to 200 later if no error occurs
        header('HTTP/1.1 500 Internal Server error');
        header('Content-Type: text/plain; encoding=utf-8');

        try {
            EidUtility::initTCA();

            // get task (function)
            $strTask = (string) $_REQUEST['task'];

            if ($strTask === 'clearCache') {
                $this->taskClearCache();
            } else {
                static::triggerErrorUnknownTask();
            }
        } catch (Exception $e) {
            static::triggerErrorUnknownException($e);
        }

        header('HTTP/1.1 ' . static::HTTP_OK);
        echo 'Done' . "\n";
    }



    /**
     * Trigger error for unknown exception caught.
     *
     * @param Exception $e unknown exception
     *
     * @return void
     */
    protected static function triggerErrorUnknownException(Exception $e): void
    {
        GeneralUtility::devLog(
            'Caught unknown Exception', 'nr_sync',
            GeneralUtility::SYSLOG_SEVERITY_ERROR,
            ['exception' => $e]
        );
        static::triggerError(self::HTTP_ERROR_UNKNOWN, 'Unknown Exception logged');
    }



    /**
     * Trigger error for unknown/invalid task name.
     *
     * @return void
     */
    protected static function triggerErrorUnknownTask(): void
    {
        static::triggerError(self::HTTP_ERROR_COMMAND, 'Task unknown');
    }



    /**
     * Trigger error for failed cache service initialising.
     *
     * @return void
     */
    protected static function triggerErrorNoService(): void
    {
        static::triggerError(
            self::HTTP_ERROR_UNKNOWN,
            'Could not find nrClearCache service'
        );
    }



    /**
     * Trigger error for missing parameter.
     *
     * @return void
     */
    protected static function triggerErrorNoParameter(): void
    {
        static::triggerError(
            self::HTTP_ERROR_COMMAND,
            'data parameter absent'
        );
    }



    /**
     * Trigger error.
     *
     * Exit execution.
     *
     * @param int $nCode      Error code.
     * @param string  $strMessage Error message.
     *
     * @return void
     */
    protected static function triggerError(int $nCode, string $strMessage): void
    {
        header('HTTP/1.1 ' . $nCode . ' ' . $strMessage);
        echo 'Error: ' . $strMessage;
        exit;
    }



    /**
     * Function for the clearCache task.
     *
     * @return void
     */
    private function taskClearCache(): void
    {
        if (! isset($_REQUEST['data'])) {
            static::triggerErrorNoParameter();
        }

        $arData = explode(',', $_REQUEST['data']);
        $this->requireClearCacheService();
        $this->runClearCacheService($arData);
    }



    /**
     * Setups the needed ClearCacheService.
     *
     * @return void
     */
    private function requireClearCacheService(): void
    {
        $this->service = GeneralUtility::makeInstanceService('nrClearCache');

        if (! is_object($this->service)) {
            static::triggerErrorNoService();
        }
    }



    /**
     * Run the service.
     *
     * @param array $arData Array with values in table:uid order.
     *
     * @return void
     */
    private function runClearCacheService(array $arData): void
    {
        global $BE_USER;

        /** @var BackendUserAuthentication $BE_USER */
        $BE_USER = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $BE_USER->start();

        // SDM-12632 try increased memory limit
        ini_set('memory_limit', '256M');

        GeneralUtility::devLog(
            'Memory usage before clear cache: ' . memory_get_peak_usage(true),
            'nr_sync',
            GeneralUtility::SYSLOG_SEVERITY_INFO
        );
        $this->service->clearCaches($arData);

        GeneralUtility::devLog(
            'Memory usage after clear cache: ' . memory_get_peak_usage(true),
            'nr_sync',
            GeneralUtility::SYSLOG_SEVERITY_INFO
        );
    }
}

/** @var Nr_Sync_Eid $robot */
$robot = GeneralUtility::makeInstance('Nr_Sync_Eid');
$robot->main();
