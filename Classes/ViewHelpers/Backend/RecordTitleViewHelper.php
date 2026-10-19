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

namespace Netresearch\Sync\ViewHelpers\Backend;

use Netresearch\Sync\Helper\TypeHelper;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper to print the "title"-value in record, $row, from table, $table.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
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
        // A page that was deleted after it was marked has no record; getRecordTitle() reads
        // an empty row the way it read NULL before: it falls back to its "no title" label
        return BackendUtility::getRecordTitle(
            'pages',
            BackendUtility::getRecord('pages', TypeHelper::toInt($this->arguments['pid'])) ?? [],
        );
    }
}
