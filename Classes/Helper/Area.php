<?php
/**
 * Part of nr_sync
 *
 * PHP version 5
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Christian Weiske <christian.weiske@netresearch.de>
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */

namespace Netresearch\Sync\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Methods to work with synchronization areas
 *
 * @package    Netresearch/TYPO3/Sync
 * @author     Christian Weiske <christian.weiske@netresearch.de>
 * @copyright  2013 Netresearch GmbH & Co.KG
 * @license    https://www.gnu.org/licenses/agpl AGPL v3
 * @link       http://www.netresearch.de
 */
class Area
{
    var $areas = array(
        0 => array(
            'name'                 => 'All',
            'description'          => 'Sync mit Live Server',
            'not_doctype'          => array(199),
            'system'               => array(
                'LIVE-AWS' => array(
                    'name'      => 'live',
                    'directory' => 'aida-aws-live',
                    'notify'    => array(
                        'type'     => 'ftp',
                        'host'     => 'uzsync11.aida.de',
                        'user'     => 'aida-aws-prod-typo3_8',
                        'password' => 'Thu2phoh',
                        'contexts' => [
                            'Production/Stage',
                        ],
                    ),
                    'report_error' => true,
                ),
                'ITG-AWS'  => array(
                    'name'      => 'itg',
                    'directory' => 'aida-aws-itg',
                    'notify'    => array(
                        'type'     => 'ftp',
                        'host'     => 'uzsync11.aida.de',
                        'user'     => 'aida-aws-itg-typo3_8',
                        'password' => 'zo6Aelow',
                        'contexts' => [
                            'Production/Stage',
                        ],
                    ),
                    'report_error' => true,
                ),
                'archive'  => array(
                    'name'      => 'archive',
                    'directory' => 'archive',
                    'notify'    => array(
                        'type'     => 'none',
                    ),
                ),
            ),
            'sync_fe_groups'       => true,
            'sync_be_groups'       => true,
            'sync_tables'          => true,
        ),
    );

    /**
     * @var array active area configuration
     */
    protected $area = [
        'id'             => 0,
        'name'           => '',
        'description'    => '',
        'not_doctype'    => [199],
        'system'         => [],
        'sync_fe_groups' => true,
        'sync_be_groups' => true,
        'sync_tables'    => true,
    ];

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     * @inject
     */
    protected $objectManager;


    /**
     * @var \TYPO3\CMS\Core\Messaging\FlashMessageService
     * @inject
     */
    protected $messageService;


    /**
     * Area constructor.
     *
     * @param integer $pId Page ID
     */
    public function __construct(int $pId)
    {
        if (isset($this->areas[$pId])) {
            // Seite ist ein Startelement eines Bereiches
            $this->area = $this->areas[$pId];
            $this->area['id'] = $pId;
        } else {
            $rootLine = BackendUtility::BEgetRootLine($pId);
            foreach ($rootLine as $element) {
                if (isset($this->areas[$element['uid']])) {
                    $this->area = $this->areas[$element['uid']];
                    $this->area['id'] = $element['uid'];
                    break;
                }
            }
        }
    }



    /**
     * Fetches the area that a content element belongs to.
     *
     * @param integer $contentID Content element ID
     *
     * @return array|false Array with keys: element, page, areaID
     */
    public static function getAreaFromContentId($contentID)
    {
        global $TYPO3_DB, $AREA;
        $ret = $TYPO3_DB->exec_SELECTquery(
            '*', 'tt_content', 'uid = ' . (int)$contentID
        );

        if (empty($ret)) {
            return false;
        }

        $arContent = $TYPO3_DB->sql_fetch_assoc($ret);
        $TYPO3_DB->sql_free_result($ret);

        if (!is_array($arContent)) {
            return false;
        }

        $ret = $TYPO3_DB->exec_SELECTquery(
            '*', 'pages', 'uid = ' . (int)$arContent['pid']
        );
        if (empty($ret)) {
            return false;
        }

        $arPage = $TYPO3_DB->sql_fetch_assoc($ret);
        $TYPO3_DB->sql_free_result($ret);

        if (! is_array($arPage)) {
            return false;
        }

        $rootline = BackendUtility::BEgetRootLine($arPage['uid']);
        foreach ($rootline as $element) {
            if (isset($AREA[$element['uid']])) {
                $arAreaConfig = $AREA[$element['uid']];
                $arAreaConfig['id'] = $element['uid'];
                break;
            }
        }
        $arReturn = array(
            'element' => $arContent,
            'page'    => $arPage,
            'areaID'  => $arAreaConfig['id'],
        );

        return $arReturn;
    }



    /**
     * Returns array of directories, this area have to write to
     *
     * @param array $arAreaConfig Config of the area in use
     *
     * @return array With the path names
     */
    public static function getAreaDirectories(array $arAreaConfig)
    {
        $arPaths = array();
        foreach ($arAreaConfig['system'] as $arSystem) {
            array_push($arPaths, $arSystem['directory']);
        }
        return $arPaths;
    }



    /**
     * Return all areas that shall get synced for the given table type
     *
     * @param array  $arAreas      Area configurations
     * @param string $strTableType Type of tables to sync, e.g. "sync_tables",
     *                             "sync_fe_groups", "sync_be_groups", "backsync_tables"
     *
     * @return self[]
     */
    public static function getMatchingAreas(array $arAreas = null, $strTableType = '')
    {
        /* @var $objectManager ObjectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        return array(
            $objectManager->get(self::class, 0),
        );
    }



    public function isDocTypeAllowed(array $record)
    {
        if (false === $record) {
            return false;
        }

        if ((isset($this->area['doctype']) && !in_array($record['doktype'], $this->area['doctype']))
            && (isset($this->area['not_doctype']) && in_array($record['doktype'], $this->area['not_doctype']))
        ) {
            return false;
        }

        return true;
    }

    public function getName()
    {
        return $this->area['name'];
    }

    public function getId()
    {
        return $this->area['id'];
    }

    public function getDescription()
    {
        return $this->area['description'];
    }

    public function getFilesToSync()
    {
        return $this->area['files_to_sync'];
    }

    public function getDirectories()
    {
        $arPaths = array();

        foreach ($this->area['system'] as $arSystem) {
            array_push($arPaths, $arSystem['directory']);
        }

        return $arPaths;
    }

    public function getNotDocType()
    {
        return (array) $this->area['not_doctype'];
    }

    public function getDocType()
    {
        return (array) $this->area['doctype'];
    }

    public function getSystems()
    {
        return (array) $this->area['system'];
    }



    /**
     * Informiert Master(LIVE) Server per zb. FTP
     *
     * @return boolean True if all went well, false otherwise
     */
    public function notifyMaster()
    {
        foreach ($this->getSystems() as $arSystem) {
            if ($this->systemIsNotifyEnabled($arSystem)) {
                switch ($arSystem['notify']['type']) {
                    case 'ftp':
                        $this->notifyMasterViaFtp($arSystem['notify']);
                        $this->addMessage('Signaled "' . $arSystem['name'] . '" target for new sync.');
                        break;
                }
            } else {
                $this->addMessage(
                    'Skipped signaling "' . $arSystem['name'] . '" target due to invalid context.'
                );
            }
        }

        return true;
    }



    /**
     * Returns true if current TYPO3_CONTEXT fits with context whitelist for system/target.
     *
     * given system.contexts = ['Production/Stage', 'Production/Foo']
     *
     * TYPO3_CONTEXT = Production/Live
     * returns false
     *
     * TYPO3_CONTEXT = Production
     * returns false
     *
     * TYPO3_CONTEXT = Production/Stage
     * returns true
     *
     * TYPO3_CONTEXT = Production/Stage/Instance01
     * returns true
     *
     * @param array $system
     * @return bool
     */
    protected function systemIsNotifyEnabled(array $system)
    {
        if (empty($system['notify']['contexts'])) {
            return false;
        }

        foreach ($system['notify']['contexts'] as $context) {
            $configuredContext = $this->objectManager->get(ApplicationContext::class, $context);

            $contextValid = strpos(
                (string) Bootstrap::getInstance()->getApplicationContext(),
                (string) $configuredContext
            );

            if ($contextValid) {
                return true;
            }
        }

        return false;
    }



    /**
     * Adds error message to message queue.
     *
     * message types are defined as class constants self::STYLE_*
     *
     * @param string $strMessage message
     * @param integer $type message type
     *
     * @return void
     */
    public function addMessage($strMessage, $type = FlashMessage::INFO)
    {
        /* @var $message FlashMessage */
        $message = $this->objectManager->get(
            FlashMessage::class, $strMessage, '', $type, true
        );

        $this->messageService->getMessageQueueByIdentifier()->addMessage($message);
    }



    /**
     * Inform the Master(LIVE) Server per FTP
     *
     * @param string[] $arFtpConfig Config of the ftp connection
     * @throws \Exception
     */
    protected function notifyMasterViaFtp(array $arFtpConfig)
    {
        $conn_id = ftp_connect($arFtpConfig['host']);

        if (!$conn_id) {
            throw new \Exception('Signal: FTP connection failed.');
        }

        $login_result = ftp_login($conn_id, $arFtpConfig['user'], $arFtpConfig['password']);

        if (!$login_result) {
            throw new \Exception('Signal: FTP auth failed.');
        }

        // TYPO-3844: enforce passive mode
        ftp_pasv($conn_id, true);

        // create trigger file
        $source_file = tempnam(sys_get_temp_dir(), 'prefix');

        if (false === ftp_put($conn_id, 'db.txt', $source_file, FTP_BINARY)) {
            ftp_quit($conn_id);
            throw new \Exception('Signal: FTP put db.txt failed.');
        }

        if (false === ftp_put($conn_id, 'files.txt', $source_file, FTP_BINARY)) {
            ftp_quit($conn_id);
            throw new \Exception('Signal: FTP put files.txt failed.');
        }

        ftp_quit($conn_id);
    }
}
