<?php
/**
 * Created by PhpStorm.
 * User: sebastian.mendel
 * Date: 2017-09-04
 * Time: 18:35
 */

namespace Netresearch\Sync;


use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extensionmanager\Utility\ConfigurationUtility;

class SyncLock
{
    /**
     * Returns message for current lock.
     *
     * @return string
     */
    public function getLockMessage()
    {
        $config = $this->getExtensionConfiguration();

        return (string) $config['syncModuleLockedMessage']['value'];
    }



    /**
     * React to requests concerning lock or unlock of the module.
     *
     * @return void
     * @throws Exception
     */
    public function handleLockRequest()
    {
        if (false === $this->receivedLockChangeRequest()) {
            return;
        }

        try {
            $this->storeLockConfiguration();
            $this->messageOk(
                'Sync module was ' . ($this->isLockRequested() ? 'locked.' : 'unlocked.')
            );
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
     */
    protected function messageOk($strMessage)
    {
        /* @var $objectManager ObjectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        /* @var $message FlashMessage */
        $message = $objectManager->get(FlashMessage::class, $strMessage);

        /* @var $messageService FlashMessageService */
        $messageService = GeneralUtility::makeInstance(
            FlashMessageService::class
        );

        $messageService->getMessageQueueByIdentifier()->addMessage($message);
    }



    /**
     * Returns true if lock state change request was sent.
     *
     * @return bool
     */
    protected function receivedLockChangeRequest()
    {
        return isset($_REQUEST['data']['lock']);
    }



    /**
     * Returns requested lock state.
     *
     * @return bool
     */
    protected function isLockRequested()
    {
        return (bool) $_REQUEST['data']['lock'];
    }



    /**
     * Returns current lock state.
     *
     * @return bool
     */
    public function isLocked()
    {
        $syncConf = $this->getExtensionConfiguration();

        return (boolean) $syncConf['syncModuleLocked']['value'];
    }



    /**
     * Returns the current extension configuration.
     *
     * @return array
     */
    protected function getExtensionConfiguration()
    {
        return $this->getConfigurationUtility()->getCurrentConfiguration('nr_sync');
    }



    /**
     * Persist lock state in extension configuration.
     *
     * @return void
     */
    protected function storeLockConfiguration()
    {
        $configurationUtility = $this->getConfigurationUtility();
        $extensionConfiguration = $configurationUtility->getCurrentConfiguration('nr_sync');

        $extensionConfiguration['syncModuleLocked']['value'] = (bool) $this->isLockRequested();

        /** @var array $nestedConfiguration */
        $nestedConfiguration = $configurationUtility->convertValuedToNestedConfiguration($extensionConfiguration);

        // i want to have updated the configuration during run time
        // so any following check will have new updated values
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['nr_sync'] = serialize(
            $nestedConfiguration
        );

        $configurationUtility->writeConfiguration($nestedConfiguration, 'nr_sync');
    }



    /**
     * @return ConfigurationUtility
     */
    protected function getConfigurationUtility()
    {
        /* @var $objectManager ObjectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        /** @var $configurationUtility ConfigurationUtility */
        $configurationUtility = $objectManager->get('TYPO3\CMS\Extensionmanager\Utility\ConfigurationUtility');

        return $configurationUtility;
    }
}