<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\File;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper to return the filename component of path.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class BasenameViewHelper extends AbstractViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'file',
            'string',
            'The name and path to the file',
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
        return basename($this->arguments['file']);
    }
}
