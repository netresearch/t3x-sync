<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use Exception;
use Netresearch\Sync\Traits\FlashMessageTrait;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Class SyncLock.
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SyncLock
{
    use FlashMessageTrait;

    /**
     * The extension configuration.
     *
     * @var ExtensionConfiguration
     */
    private readonly ExtensionConfiguration $extensionConfiguration;

    /**
     * SyncLock constructor.
     *
     * @param ExtensionConfiguration $extensionConfiguration
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
    }

    /**
     * React to requests concerning lock or unlock of the module.
     *
     * @throws Exception
     */
    public function handleModuleLock(): void
    {
        if (!$this->receivedLockChangeRequest()) {
            return;
        }

        try {
            $this->storeLockConfiguration();
            $this->addInfoMessage('Sync module was ' . ($this->isLockRequested() ? 'locked.' : 'unlocked.'));
        } catch (Exception $exception) {
            throw new RuntimeException(
                'Error in nr_sync configuration: '
                . $exception->getMessage()
                . ' Please check configuration in the Extension Manager.', $exception->getCode(), $exception
            );
        }
    }

    /**
     * Returns true if lock state change request was sent.
     *
     * @return bool
     */
    private function receivedLockChangeRequest(): bool
    {
        return isset($_REQUEST['data']['lock']);
    }

    /**
     * Returns requested lock state.
     *
     * @return bool
     */
    private function isLockRequested(): bool
    {
        return (bool) $_REQUEST['data']['lock'];
    }

    /**
     * Returns message for current lock.
     *
     * @return string
     */
    public function getLockMessage(): string
    {
        try {
            return $this->extensionConfiguration->get('nr_sync')['syncModuleLockedMessage'];
        } catch (Exception) {
            return '';
        }
    }

    /**
     * Returns current lock state.
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        try {
            return (bool) $this->extensionConfiguration->get('nr_sync')['syncModuleLocked'];
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Persist lock state in extension configuration.
     */
    private function storeLockConfiguration(): void
    {
        try {
            $configuration                     = $this->extensionConfiguration->get('nr_sync');
            $configuration['syncModuleLocked'] = $this->isLockRequested();

            // Updated the configuration during run time, so any following check will have new updated values
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_sync'] = $configuration;

            // Write new/updated configuration
            $this->extensionConfiguration->set('nr_sync', $configuration);
        } catch (Exception) {
        }
    }
}
