<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Helper;

use Netresearch\Sync\Exception;
use Netresearch\Sync\Traits\FlashMessageTrait;
use Netresearch\Sync\Traits\TranslationTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function in_array;

/**
 * Methods to work with synchronization areas.
 *
 * @author  Christian Weiske <christian.weiske@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Area
{
    use TranslationTrait;
    use FlashMessageTrait;

    /**
     * @var array<int, array<string, int|string|bool|mixed>>
     */
    public array $areas = [];

    /**
     * Active area configuration.
     *
     * @var array<string, int|string|bool|mixed>
     */
    protected array $area = [
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
     * The system which is selected for sync.
     *
     * @var string
     */
    protected string $selectedTarget = 'all';

    /**
     * Constructor.
     *
     * @param int    $pid    The page ID
     * @param string $target The system which is selected for sync
     *
     * @throws Exception
     */
    public function __construct(int $pid, string $target = 'all')
    {
        $this->loadSyncAreas();

        $this->selectedTarget = $target;

        if (isset($this->areas[$pid])) {
            $this->area       = $this->areas[$pid];
            $this->area['id'] = $pid;

            $this->removeUnwantedSystems();
        } else {
            $rootLine = BackendUtility::BEgetRootLine($pid);

            foreach ($rootLine as $element) {
                if (isset($this->areas[$element['uid']])) {
                    $this->area       = $this->areas[$element['uid']];
                    $this->area['id'] = $element['uid'];

                    $this->removeUnwantedSystems();
                    break;
                }
            }
        }
    }

    /**
     * @return void
     */
    private function removeUnwantedSystems(): void
    {
        if ($this->selectedTarget === 'all') {
            return;
        }

        foreach ($this->area['system'] as $key => $system) {
            if ($this->selectedTarget === '') {
                continue;
            }

            if ($this->selectedTarget === $key) {
                continue;
            }

            if (strtolower($key) === 'archive') {
                continue;
            }

            unset($this->area['system'][$key]);
        }
    }

    /**
     * Return all areas that shall get synced for the given table type.
     *
     * @param string $target Target to sync to
     *
     * @return self[]
     */
    public static function getMatchingAreas(string $target = 'all'): array
    {
        return [
            GeneralUtility::makeInstance(self::class, 0, $target),
        ];
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return bool
     */
    public function isDocTypeAllowed(array $record): bool
    {
        return !(isset($this->area['doctype'], $this->area['not_doctype'])
            && in_array($record['doktype'], $this->area['not_doctype'], true)
            && !in_array($record['doktype'], $this->area['doctype'], true));
    }

    /**
     * Returns the name of the area.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->area['name'] ?? '';
    }

    /**
     * Returns the ID of the area.
     *
     * @return int
     *
     * @deprecated
     */
    public function getId(): int
    {
        return $this->area['id'] ?? 0;
    }

    /**
     * Returns the description of the area.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->area['description'] ?? '';
    }

    /**
     * Returns the files which should be synced.
     *
     * @return string[]
     */
    public function getFilesToSync(): array
    {
        return $this->area['files_to_sync'] ?? [];
    }

    /**
     * Returns an array with the directories where the sync files are stored.
     *
     * @return string[]
     */
    public function getDirectories(): array
    {
        $paths = [];

        foreach ($this->getSystems() as $system) {
            if (!isset($system['directory'])) {
                continue;
            }

            if ($system['directory'] === '') {
                continue;
            }

            $paths[] = $system['directory'];
        }

        return $paths;
    }

    /**
     * Returns an array with the directories where the url files should be stored.
     *
     * @return string[]
     */
    public function getUrlDirectories(): array
    {
        $paths = [];

        foreach ($this->getSystems() as $system) {
            if (!isset($system['url-path'])) {
                continue;
            }

            if ($system['url-path'] === '') {
                continue;
            }

            $paths[] = $system['url-path'];
        }

        return $paths;
    }

    /**
     * Returns the doc types wich should be ignored for sync.
     *
     * @return int[]
     */
    public function getNotDocType(): array
    {
        return $this->area['not_doctype'] ?? [];
    }

    /**
     * Returns the synchronizeable doc types.
     *
     * @return int[]
     */
    public function getDocType(): array
    {
        return $this->area['doctype'] ?? [];
    }

    /**
     * Returns the systems.
     *
     * @return array<string, array{
     *     name: string,
     *     directory: string,
     *     url-path: string,
     *     notify: array<string, string|string[]>,
     *     hide: bool
     * }>
     */
    public function getSystems(): array
    {
        return $this->area['system'] ?? [];
    }

    /**
     * Returns the data of the given system.
     *
     * @param string $systemName
     *
     * @return array{
     *      name: string,
     *      directory: string,
     *      url-path: string,
     *      notify: array<string, string|string[]>,
     *      hide: bool
     *  }
     */
    public function getSystem(string $systemName): array
    {
        return $this->getSystems()[$systemName] ?? [];
    }

    /**
     * Informs master (LIVE) server via e.g. FTP.
     *
     * @return void
     */
    public function notifyMaster(): void
    {
        foreach ($this->getSystems() as $system) {
            if (!$this->systemIsNotifyEnabled($system)) {
                continue;
            }

            if (isset($system['notify']['type'])
                && ($system['notify']['type'] === 'ftp')
            ) {
                try {
                    $this->notifyMasterViaFtp($system['notify']);

                    $this->addMessage(
                        $this->getLabel(
                            'message.notify_success',
                            [
                                '{target}' => $system['name'],
                            ]
                        )
                    );
                } catch (\Exception) {
                    $this->addErrorMessage(
                        $this->getLabel(
                            'message.notify_failed',
                            [
                                '{target}' => $system['name'],
                            ]
                        )
                    );
                }
            } else {
                $this->addMessage(
                    $this->getLabel(
                        'message.notify_unknown',
                        [
                            '{target}'      => $system['name'],
                            '{notify_type}' => $system['notify']['type'],
                        ]
                    )
                );
            }
        }
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
     * @param array{name: string, directory: string, url-path: string, notify: array<string, string|string[]>, hide: bool} $system
     *
     * @return bool
     */
    protected function systemIsNotifyEnabled(array $system): bool
    {
        if (!isset($system['notify']['contexts'])) {
            $this->addMessage(
                $this->getLabel(
                    'message.notify_disabled',
                    [
                        '{target}' => $system['name'],
                    ]
                )
            );

            return false;
        }

        foreach ($system['notify']['contexts'] as $context) {
            $configuredContext = GeneralUtility::makeInstance(ApplicationContext::class, $context);

            if (str_starts_with((string) Environment::getContext(), (string) $configuredContext)) {
                return true;
            }
        }

        $this->addMessage(
            $this->getLabel(
                'message.notify_skipped_context',
                [
                    '{target}'           => $system['name'],
                    '{allowed_contexts}' => implode(', ', $system['notify']['contexts']),
                ]
            )
        );

        return false;
    }

    /**
     * Inform the Master(LIVE) Server per FTP.
     *
     * @param string[] $ftpConfig Config of the ftp connection
     *
     * @throws Exception
     */
    protected function notifyMasterViaFtp(array $ftpConfig): void
    {
        // Suppress the PHP warning message if the host is invalid
        $connection = @ftp_connect($ftpConfig['host'] ?? '');

        if (!$connection) {
            throw new Exception('Signal: FTP connection failed.');
        }

        $loginResult = ftp_login($connection, $ftpConfig['user'], $ftpConfig['password']);

        if (!$loginResult) {
            throw new Exception('Signal: FTP auth failed.');
        }

        // Enforce passive mode
        ftp_pasv($connection, true);

        // Create trigger file
        $sourceFile = tempnam(sys_get_temp_dir(), 'prefix');

        if (ftp_put($connection, 'db.txt', $sourceFile) === false) {
            ftp_close($connection);
            throw new Exception('Signal: FTP put db.txt failed.');
        }

        if (ftp_put($connection, 'files.txt', $sourceFile) === false) {
            ftp_close($connection);
            throw new Exception('Signal: FTP put files.txt failed.');
        }

        ftp_close($connection);
    }

    /**
     * Load the Area configuration.
     *
     * @throws Exception
     */
    protected function loadSyncAreas(): void
    {
        if (file_exists(Environment::getPublicPath() . '/typo3conf/SyncAreaConfiguration.php') === false) {
            throw new Exception(
                'Area configuration s missing, please provide a Config file in path '
                . '`public/typo3conf/SyncAreaConfiguration.php`'
            );
        }

        $this->areas = include Environment::getPublicPath() . '/typo3conf/SyncAreaConfiguration.php';
    }
}
