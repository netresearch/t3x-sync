<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\Backend;

use TYPO3\CMS\Fluid\ViewHelpers\Be\UriViewHelper as BackendUriViewHelper;

/**
 * A ViewHelper for creating URIs to modules.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class UriViewHelper extends BackendUriViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

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
     */
    public function render(): string
    {
        $this->arguments['parameters'] = [
            'lock' => [
                $this->arguments['area'] => $this->arguments['lock'],
            ],
            'id' => $this->arguments['pid'],
        ];

        return parent::render();
    }
}
