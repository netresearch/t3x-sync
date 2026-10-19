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

namespace Netresearch\Sync\ViewHelpers\Format;

use Netresearch\Sync\Helper\TypeHelper;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper to print out the username.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
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
            true,
        );
    }

    /**
     * Render stuff.
     *
     * @return string
     */
    public function render(): string
    {
        $rawId = $this->arguments['id'];
        $id    = TypeHelper::toInt($rawId);

        if ($id > 0) {
            $user = $this->getBackendUserAuthentication()
                ->getRawUserByUid($id);

            $realName = TypeHelper::toString(TypeHelper::arrayPath($user, 'realName'));
            $userName = TypeHelper::toString(TypeHelper::arrayPath($user, 'username'));

            return ($realName !== '' ? $realName : $userName) . ' #' . $id;
        }

        return ($rawId === 0) ? 'SYSTEM' : 'UNKNOWN';
    }

    /**
     * @return BackendUserAuthentication
     */
    private function getBackendUserAuthentication(): BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'];
        assert($backendUser instanceof BackendUserAuthentication);

        return $backendUser;
    }
}
