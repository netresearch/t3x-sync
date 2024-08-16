<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\Backend;

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper;

/**
 * A ViewHelper for creating URIs to modules.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UriViewHelper extends AbstractBackendViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'route',
            'string',
            'The name of the route',
            true
        );

        $this->registerArgument(
            'pid',
            'int',
            'The page UID',
            true
        );

        $this->registerArgument(
            'area',
            'string',
            'The area name to lock/unlock',
            true
        );

        $this->registerArgument(
            'lock',
            'int',
            '1 or 0 to lock/unlock the area',
            true
        );
    }

    /**
     * Render stuff.
     *
     * @return string
     *
     * @throws RouteNotFoundException
     */
    public function render(): string
    {
        return (string) GeneralUtility::makeInstance(UriBuilder::class)
            ->buildUriFromRoute(
                $this->arguments['route'],
                [
                    'id'   => $this->arguments['pid'],
                    'lock' => [
                        $this->arguments['area'] => $this->arguments['lock'],
                    ],
                ]
            );
    }
}
