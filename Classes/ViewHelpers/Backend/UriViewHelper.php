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
            'The page id',
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
            'bool',
            'TRUE or FALSE to lock/unlock the area',
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
        $parameters = [
            'lock' => [
                $this->arguments['area'] => $this->arguments['lock'],
            ],
            'id' => $this->arguments['pid'],
        ];

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        return (string) $uriBuilder->buildUriFromRoute(
            $this->arguments['route'],
            $parameters
        );
    }
}
