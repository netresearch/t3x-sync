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
    var $areas = [
        0 => [
            'name'                 => 'All',
            'description'          => 'Sync to live server',
            'not_doctype'          => [],
            'system'               => [
                'LIVE-AWS' => [
                    'name'      => 'Live',
                    'directory' => 'aida-aws-live',
                    'notify'    => [
                        'type'     => 'ftp',
                        'host'     => 'uzsync11.aida.de',
                        'user'     => 'aida-aws-prod-typo3_8',
                        'password' => 'Thu2phoh',
                        'contexts' => [
                            'Production/Stage',
                        ],
                    ],
                ],
                'ITG-AWS'  => [
                    'name'      => 'ITG',
                    'directory' => 'aida-aws-itg',
                    'notify'    => [
                        'type'     => 'ftp',
                        'host'     => 'uzsync11.aida.de',
                        'user'     => 'aida-aws-itg-typo3_8',
                        'password' => 'zo6Aelow',
                        'contexts' => [
                            'Production/Stage',
                        ],
                    ],
                ],
                'archive'  => [
                    'name'      => 'Archive',
                    'directory' => 'archive',
                    'notify'    => [
                        'type'     => 'none',
                    ],
                    'hide'      => true,
                ],
            ],
            'sync_fe_groups'       => true,
            'sync_be_groups'       => true,
            'sync_tables'          => true,
        ],
    ];

    /**
     * @var array active area configuration
     */
    protected $area = [
        'id'             => 0,
        'name'           => '',
        'description'    => '',
        'not_doctype'    => [],
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
                    default:
                        $this->addMessage(
                            'Skipped signaling "' . $arSystem['name'] . '" target.'
                            . ' Unknown notify type: "' . $arSystem['notify']['type'] . '".'
                        );
                }
            } else {
                $this->addMessage(
                    'Skipped signaling "' . $arSystem['name'] . '" target due to invalid context.'
                    . ' Allowed contexts: ' . implode(', ', $arSystem['notify']['contexts'])
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
            $this->addMessage(
                'Skipped signaling "' . $system['name'] . '" target due to signaling disabled.'
            );
            return false;
        }

        foreach ($system['notify']['contexts'] as $context) {
            $configuredContext = $this->objectManager->get(ApplicationContext::class, $context);

            $contextCheck = strpos(
                (string) Bootstrap::getInstance()->getApplicationContext(),
                (string) $configuredContext
            );

            if (0 === $contextCheck) {
                return true;
            }
        }

        $this->addMessage(
            'Skipped signaling "' . $system['name'] . '" target due to invalid context.'
            . ' Allowed contexts: ' . implode(', ', $system['notify']['contexts'])
        );

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
