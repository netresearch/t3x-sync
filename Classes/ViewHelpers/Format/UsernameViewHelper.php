<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\Format;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper to print out the username.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UsernameViewHelper extends AbstractViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'id',
            'int',
            'The UID of the user',
            true
        );
    }

    /**
     * Render stuff.
     *
     * @return string
     */
    public function render(): string
    {
        if ($this->arguments['id']) {
            $user = $this->getBackendUser()->getRawUserByUid($this->arguments['id']);
            return $user['realName'] . ' #' . $this->arguments['id'];
        }

        return ($this->arguments['id'] === 0) ? 'SYSTEM' : 'UNKNOWN';
    }

    /**
     * @return BackendUserAuthentication
     */
    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
