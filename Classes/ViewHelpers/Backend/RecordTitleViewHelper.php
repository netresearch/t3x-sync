<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\ViewHelpers\Backend;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper to print the "title"-value in record, $row, from table, $table.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class RecordTitleViewHelper extends AbstractViewHelper
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
    }

    /**
     * Render stuff.
     *
     * @return string
     */
    public function render(): string
    {
        return BackendUtility::getRecordTitle(
            'pages',
            BackendUtility::getRecord('pages', $this->arguments['pid'])
        );
    }
}
