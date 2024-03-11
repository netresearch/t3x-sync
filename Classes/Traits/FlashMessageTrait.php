<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Traits;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FlashMessageTrait.
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
trait FlashMessageTrait
{
    /**
     * Adds error message to message queue. Message types are defined as class constants self::STYLE_*.
     *
     * @param string                     $message  The message
     * @param string                     $title    The message title
     * @param ContextualFeedbackSeverity $severity The message severity
     */
    public function createFlashMessage(
        string $message,
        string $title = '',
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::INFO
    ): void {
        /** @var FlashMessage $flashMessage */
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            true
        );

        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($flashMessage);
    }

    /**
     * Creates an error message.
     *
     * @param string $message Error message
     *
     * @return void
     */
    public function addErrorMessage(string $message): void
    {
        $this->createFlashMessage($message, '', ContextualFeedbackSeverity::ERROR);
    }

    /**
     * Creates an error message.
     *
     * @param string $message Error message
     *
     * @return void
     */
    public function addInfoMessage(string $message): void
    {
        $this->createFlashMessage($message);
    }

    /**
     * Creates an error message.
     *
     * @param string $message Error message
     *
     * @return void
     */
    public function addWarningMessage(string $message): void
    {
        $this->createFlashMessage($message, '', ContextualFeedbackSeverity::WARNING);
    }

    /**
     * Creates an error message.
     *
     * @param string $message Error message
     *
     * @return void
     */
    public function addSuccessMessage(string $message): void
    {
        $this->createFlashMessage($message, '', ContextualFeedbackSeverity::OK);
    }

    /**
     * Creates an error message.
     *
     * @param string $message Error message
     *
     * @return void
     */
    public function addNoticeMessage(string $message): void
    {
        $this->createFlashMessage($message, '', ContextualFeedbackSeverity::NOTICE);
    }

    /**
     * Adds error message to message queue.
     *
     * message types are defined as class constants self::STYLE_*
     *
     * @param string                         $message message
     * @param int|ContextualFeedbackSeverity $type    message type
     *
     * @return void
     */
    public function addMessage(
        string $message,
        int|ContextualFeedbackSeverity $type = ContextualFeedbackSeverity::INFO
    ): void {
        $this->createFlashMessage($message, '', $type);
    }
}
