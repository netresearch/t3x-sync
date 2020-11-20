<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class SyncLock
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SyncLock
{
    /**
     * The extension configuration.
     *
     * @var ExtensionConfiguration
     */
    private $extensionConfiguration;

    /**
     * SyncLock constructor.
     */
    public function __construct()
    {
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }

    /**
     * Returns message for current lock.
     *
     * @return string
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getLockMessage(): string
    {
        $syncConf = $this->extensionConfiguration->get('nr_sync');
        return $syncConf['syncModuleLockedMessage'];
    }

    /**
     * React to requests concerning lock or unlock of the module.
     *
     * @throws Exception
     */
    public function handleLockRequest(): void
    {
        if (!$this->receivedLockChangeRequest()) {
            return;
        }

        try {
            $this->storeLockConfiguration();
            $this->messageOk('Sync module was ' . ($this->isLockRequested() ? 'locked.' : 'unlocked.'));
        } catch (\Exception $exception) {
            throw new Exception(
                'Error in nr_sync configuration: '
                . $exception->getMessage()
                . ' Please check configuration in the Extension Manager.'
            );
        }
    }

    /**
     * Send OK message to user.
     *
     * @param string $strMessage Message to user
     *
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function messageOk(string $strMessage): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        /** @var FlashMessage $message */
        $message = $objectManager->get(FlashMessage::class, $strMessage);

        /** @var FlashMessageService $messageService */
        $messageService = GeneralUtility::makeInstance(FlashMessageService::class);

        $messageService->getMessageQueueByIdentifier()->addMessage($message);
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
     * Returns current lock state.
     *
     * @return bool
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function isLocked(): bool
    {
        $syncConf = $this->extensionConfiguration->get('nr_sync');
        return (bool) $syncConf['syncModuleLocked'];
    }

    /**
     * Persist lock state in extension configuration.
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    private function storeLockConfiguration(): void
    {
        $configuration = $this->extensionConfiguration->get('nr_sync');
        $configuration['syncModuleLocked'] = $this->isLockRequested();

        // Updated the configuration during run time, so any following check will have new updated values
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_sync'] = $configuration;

        // Write new/updated configuration
        $this->extensionConfiguration->set('nr_sync', '', $configuration);
    }
}
