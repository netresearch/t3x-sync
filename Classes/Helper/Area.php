<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Helper;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Methods to work with synchronization areas
 *
 * @author  Christian Weiske <christian.weiske@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Area
{
    /**
     * @var array[]
     */
    private $areas = [
        0 => [
            'name'                 => 'All',
            'description'          => 'Sync to live server',
            'not_doctype'          => [],
            'system'               => [
                'LIVE-AWS' => [
                    'name'      => 'Live',
                    'directory' => 'aida-aws-live',
                    'url-path'  => 'aida-aws-live/url',
                    'notify'    => [
                        'type'     => 'ftp',
                        'host'     => 'uzsync12.aida.de',
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
                    'url-path'  => 'aida-aws-itg/url',
                    'notify'    => [
                        'type'     => 'ftp',
                        'host'     => 'uzsync12.aida.de',
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
                    'url-path'  => 'url/archive',
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
     * Active area configuration.
     *
     * @var array
     */
    private $area = [
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
     * @var FlashMessageService
     */
    private $flashMessageService;

    /**
     * Area constructor.
     *
     * @param int $pid The page ID
     */
    public function __construct(int $pid)
    {
        $this->flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);

        if (isset($this->areas[$pid])) {
            $this->area = $this->areas[$pid];
            $this->area['id'] = $pid;
        } else {
            $rootLine = BackendUtility::BEgetRootLine($pid);

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
     * @return Area[]
     */
    public static function getMatchingAreas(array $arAreas = null, $strTableType = ''): array
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        return [
            $objectManager->get(self::class, 0),
        ];
    }

    /**
     * @param array $record
     *
     * @return bool
     */
    public function isDocTypeAllowed(array $record): bool
    {
        if ($record === false) {
            return false;
        }

        if (isset($this->area['doctype'], $this->area['not_doctype'])
            && !\in_array($record['doktype'], $this->area['doctype'], true)
            && \in_array($record['doktype'], $this->area['not_doctype'], true)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Returns the name of AREA
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->area['name'];
    }

    /**
     * Returns the ID of the area
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->area['id'];
    }

    /**
     * Returns the description of the area
     *
     * @return mixed
     */
    public function getDescription()
    {
        return $this->area['description'];
    }

    /**
     * Returns the files which should be synced
     *
     * @return mixed
     */
    public function getFilesToSync()
    {
        return $this->area['files_to_sync'];
    }

    /**
     * Returns a array with the directories where the syncfiles are stored
     *
     * @return array
     */
    public function getDirectories(): array
    {
        $arPaths = [];

        foreach ($this->area['system'] as $arSystem) {
            $arPaths[] = $arSystem['directory'];
        }

        return $arPaths;
    }

    /**
     * Returns a array with the directories where the url files should be stored
     *
     * @return array
     */
    public function getUrlDirectories(): array
    {
        $arPaths = [];

        foreach ($this->area['system'] as  $arSystem) {
            if (empty($arSystem['url-path'])) {
                continue;
            }

            $arPaths[] = $arSystem['url-path'];
        }

        return $arPaths;
    }

    /**
     * Returns the doctypes wich should be ignored for sync
     *
     * @return array
     */
    public function getNotDocType(): array
    {
        return (array) $this->area['not_doctype'];
    }

    /**
     * Returns the syncabel docktypes
     *
     * @return array
     */
    public function getDocType(): array
    {
        return (array) $this->area['doctype'];
    }

    /**
     * Returns the systems
     *
     * @return array
     */
    public function getSystems(): array
    {
        return (array) $this->area['system'];
    }

    /**
     * Informiert Master(LIVE) Server per zb. FTP
     *
     * @return bool True if all went well, false otherwise
     *
     * @throws \Exception
     */
    public function notifyMaster(): bool
    {
        foreach ($this->getSystems() as $arSystem) {
            if ($this->systemIsNotifyEnabled($arSystem)) {
                if ($arSystem['notify']['type'] === 'ftp') {
                    $this->notifyMasterViaFtp($arSystem['notify']);
                    $this->addMessage('Signaled "' . $arSystem['name'] . '" target for new sync.');
                } else {
                    $this->addMessage(
                        'Skipped signaling "' . $arSystem['name'] . '" target.'
                        . ' Unknown notify type: "' . $arSystem['notify']['type'] . '".'
                    );
                }
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
     *
     * @return bool
     */
    private function systemIsNotifyEnabled(array $system): bool
    {
        if (empty($system['notify']['contexts'])) {
            $this->addMessage(
                'Skipped signaling "' . $system['name'] . '" target due to signaling disabled.'
            );

            return false;
        }

        foreach ($system['notify']['contexts'] as $context) {
            $configuredContext = GeneralUtility::makeInstance(ApplicationContext::class, $context);

            $contextCheck = strpos(
                (string) Environment::getContext(),
                (string) $configuredContext
            );

            if ($contextCheck === 0) {
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
     * Adds error message to message queue. Message types are defined as class constants self::STYLE_*.
     *
     * @param string $message The message
     * @param int    $type    The message type
     */
    public function addMessage(string $message, int $type = FlashMessage::INFO): void
    {
        /** @var FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class, $message, '', $type, true
        );

        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($flashMessage);
    }

    /**
     * Inform the Master(LIVE) Server per FTP
     *
     * @param string[] $ftpConfig Config of the ftp connection
     *
     * @throws \Exception
     */
    private function notifyMasterViaFtp(array $ftpConfig): void
    {
        $connection = ftp_connect($ftpConfig['host']);

        if (!$connection) {
            throw new \Exception('Signal: FTP connection failed.');
        }

        $loginResult = ftp_login($connection, $ftpConfig['user'], $ftpConfig['password']);

        if (!$loginResult) {
            throw new \Exception('Signal: FTP auth failed.');
        }

        // TYPO-3844: enforce passive mode
        ftp_pasv($connection, true);

        // create trigger file
        $sourceFile = tempnam(sys_get_temp_dir(), 'prefix');

        if (ftp_put($connection, 'db.txt', $sourceFile, FTP_BINARY) === false) {
            ftp_close($connection);
            throw new \Exception('Signal: FTP put db.txt failed.');
        }

        if (ftp_put($connection, 'files.txt', $sourceFile, FTP_BINARY) === false) {
            ftp_close($connection);
            throw new \Exception('Signal: FTP put files.txt failed.');
        }

        ftp_close($connection);
    }
}
