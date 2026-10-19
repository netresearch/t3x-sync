<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Helper;

use FTP\Connection;
use Netresearch\Sync\Exception;
use Netresearch\Sync\Traits\FlashMessageTrait;
use Netresearch\Sync\Traits\TranslationTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function in_array;
use function is_array;
use function is_string;

/**
 * Methods to work with synchronization areas.
 *
 * @author  Christian Weiske <christian.weiske@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 *
 * @phpstan-type SystemConfig array{
 *     name: string,
 *     directory: string,
 *     url-path: string,
 *     notify: array<string, mixed>,
 *     hide: bool
 * }
 */
class Area
{
    use TranslationTrait;
    use FlashMessageTrait;

    /**
     * The path to the default area configuration file, relative to the public web folder.
     *
     * @var string
     */
    protected string $defaultAreaConfigurationFile = __DIR__ . '/../../Configuration/DefaultAreaConfiguration.php';

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
                $uid = TypeHelper::toInt(TypeHelper::arrayPath($element, 'uid'));

                if (isset($this->areas[$uid])) {
                    $this->area       = $this->areas[$uid];
                    $this->area['id'] = $uid;

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

        $systems = TypeHelper::toArray($this->area['system'] ?? null);

        foreach (array_keys($systems) as $key) {
            if ($this->selectedTarget === '') {
                continue;
            }

            if ($this->selectedTarget === $key) {
                continue;
            }

            if (strtolower((string) $key) === 'archive') {
                continue;
            }

            unset($systems[$key]);
        }

        $this->area['system'] = $systems;
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
     * @param array<mixed> $record
     *
     * @return bool
     */
    public function isDocTypeAllowed(array $record): bool
    {
        return !(isset($this->area['doctype'], $this->area['not_doctype'])
            && in_array($record['doktype'] ?? null, TypeHelper::toArray($this->area['not_doctype']), true)
            && !in_array($record['doktype'] ?? null, TypeHelper::toArray($this->area['doctype']), true));
    }

    /**
     * Returns the name of the area.
     *
     * @return string
     */
    public function getName(): string
    {
        return TypeHelper::toString($this->area['name'] ?? '');
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
        return TypeHelper::toInt($this->area['id'] ?? 0);
    }

    /**
     * Returns the description of the area.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return TypeHelper::toString($this->area['description'] ?? '');
    }

    /**
     * Returns the files which should be synced.
     *
     * @return string[]
     */
    public function getFilesToSync(): array
    {
        return TypeHelper::toStringList($this->area['files_to_sync'] ?? null);
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
        return TypeHelper::toIntList($this->area['not_doctype'] ?? null);
    }

    /**
     * Returns the synchronizeable doc types.
     *
     * @return int[]
     */
    public function getDocType(): array
    {
        return TypeHelper::toIntList($this->area['doctype'] ?? null);
    }

    /**
     * Returns the systems.
     *
     * Entries that are no array are skipped; missing or mistyped values of an entry
     * are replaced by '' (name, directory, url-path), [] (notify) or false (hide).
     *
     * @return array<string, SystemConfig>
     */
    public function getSystems(): array
    {
        $systems = [];

        foreach (TypeHelper::toArray($this->area['system'] ?? null) as $key => $system) {
            if (!is_array($system)) {
                continue;
            }

            $notify = [];

            foreach (TypeHelper::toArray($system['notify'] ?? null) as $notifyKey => $value) {
                $notify[(string) $notifyKey] = $value;
            }

            $systems[(string) $key] = [
                'name'      => TypeHelper::toString($system['name'] ?? ''),
                'directory' => TypeHelper::toString($system['directory'] ?? ''),
                'url-path'  => TypeHelper::toString($system['url-path'] ?? ''),
                'notify'    => $notify,
                'hide'      => (bool) ($system['hide'] ?? false),
            ];
        }

        return $systems;
    }

    /**
     * Returns the data of the given system.
     *
     * @param string $systemName
     *
     * @return SystemConfig|array{} The system, or an empty array if the area has no system of that name
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
                            ],
                        ),
                    );
                } catch (\Exception) {
                    $this->addErrorMessage(
                        $this->getLabel(
                            'message.notify_failed',
                            [
                                '{target}' => $system['name'],
                            ],
                        ),
                    );
                }
            } else {
                $this->addMessage(
                    $this->getLabel(
                        'message.notify_unknown',
                        [
                            '{target}'      => $system['name'],
                            '{notify_type}' => TypeHelper::toString($system['notify']['type'] ?? ''),
                        ],
                    ),
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
     * @param SystemConfig $system
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
                    ],
                ),
            );

            return false;
        }

        $contexts = TypeHelper::toStringList($system['notify']['contexts']);

        foreach ($contexts as $context) {
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
                    '{allowed_contexts}' => implode(', ', $contexts),
                ],
            ),
        );

        return false;
    }

    /**
     * Inform the Master(LIVE) Server per FTP.
     *
     * Uploads two empty trigger files. The connection uses FTP over TLS (explicit AUTH TLS) unless the
     * notify configuration sets 'tls' => false; with TLS, a server that does not offer it refuses the login.
     *
     * @param array<string, mixed> $ftpConfig Config of the ftp connection
     *
     * @throws Exception
     */
    protected function notifyMasterViaFtp(array $ftpConfig): void
    {
        $connection = $this->openFtpConnection(
            TypeHelper::toString($ftpConfig['host'] ?? ''),
            ($ftpConfig['tls'] ?? true) !== false,
        );

        if ($connection === false) {
            throw new Exception('Signal: FTP connection failed.');
        }

        try {
            // nosemgrep: php.lang.security.ftp-use.ftp-use -- FTP over TLS unless the area configuration opts out
            if (!ftp_login($connection, TypeHelper::toString($ftpConfig['user'] ?? ''), TypeHelper::toString($ftpConfig['password'] ?? ''))) {
                throw new Exception('Signal: FTP auth failed.');
            }

            // Enforce passive mode
            // nosemgrep: php.lang.security.ftp-use.ftp-use
            ftp_pasv($connection, true);

            // Create trigger file
            $sourceFile = tempnam(sys_get_temp_dir(), 'nrsync_');

            if ($sourceFile === false) {
                throw new Exception('Signal: could not create the trigger file.');
            }

            try {
                foreach (['db.txt', 'files.txt'] as $triggerFile) {
                    // nosemgrep: php.lang.security.ftp-use.ftp-use
                    if (!ftp_put($connection, $triggerFile, $sourceFile)) {
                        throw new Exception('Signal: FTP put ' . $triggerFile . ' failed.');
                    }
                }
            } finally {
                // nosemgrep: php.lang.security.unlink-use.unlink-use -- $sourceFile is the return value of tempnam() above; no request data reaches it
                unlink($sourceFile);
            }
        } finally {
            // nosemgrep: php.lang.security.ftp-use.ftp-use
            ftp_close($connection);
        }
    }

    /**
     * Opens the FTP connection, with TLS unless $useTls is FALSE.
     *
     * @param string $host
     * @param bool   $useTls
     */
    protected function openFtpConnection(string $host, bool $useTls): Connection|false
    {
        if ($useTls && !function_exists('ftp_ssl_connect')) {
            return false;
        }

        // Swallow the PHP warning for an invalid host; the caller handles the FALSE result
        set_error_handler(static fn (): bool => true);

        try {
            if ($useTls) {
                // nosemgrep: php.lang.security.ftp-use.ftp-use -- FTP over TLS, the transport the rule asks for
                return ftp_ssl_connect($host);
            }

            // nosemgrep: php.lang.security.ftp-use.ftp-use -- plain FTP only when the area configuration sets 'tls' => false
            return ftp_connect($host);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Load the Area configuration.
     */
    protected function loadSyncAreas(): void
    {
        $this->areas = $this->getAreaConfiguration();
    }

    /**
     * Get the file location of the sync area configuration file, currently the path and filename.
     *
     * @return string
     */
    public function getAreaConfigurationFileLocation(): string
    {
        // For composer-based installations, the file is in config/system/sync-area-configuration.php
        if (Environment::getProjectPath() !== Environment::getPublicPath()) {
            return Environment::getConfigPath() . '/system/sync-area-configuration.php';
        }

        return Environment::getLegacyConfigPath() . '/system/sync-area-configuration.php';
    }

    /**
     * Return default area configuration file location.
     *
     * @return string
     */
    public function getDefaultConfigurationFileLocation(): string
    {
        return $this->defaultAreaConfigurationFile;
    }

    /**
     * Returns the area configuration array.
     *
     * @return array<int, array<string, int|string|bool|mixed>>
     */
    public function getAreaConfiguration(): array
    {
        $configurationFile = $this->getAreaConfigurationFileLocation();

        if (!is_file($configurationFile)) {
            $configurationFile = $this->getDefaultConfigurationFileLocation();
        }

        $areas = [];

        foreach (TypeHelper::toArray(require $configurationFile) as $pid => $area) {
            if (!is_array($area)) {
                continue;
            }

            $config = [];

            foreach ($area as $key => $value) {
                if (is_string($key)) {
                    $config[$key] = $value;
                }
            }

            $areas[(int) $pid] = $config;
        }

        return $areas;
    }
}
