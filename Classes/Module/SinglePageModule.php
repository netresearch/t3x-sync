<?php

/**
 * This file is part of the package netresearch/nrc-resco.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\Sync\Module;

use Netresearch\Sync\SinglePageSyncModuleInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Class SinglePageModule
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class SinglePageModule extends BaseModule implements SinglePageSyncModuleInterface
{
    /**
     * The name of the sync module to be displayed in sync module selection menu.
     *
     * @var string
     */
    protected $name = 'Single pages with content';

    /**
     * Base name of the sync file.
     *
     * @var string
     */
    protected $dumpFileName = 'partly-pages.sql';

    /**
     * Tables which should be synchronized.
     *
     * @var string[]
     */
    protected $tables = [
        'pages',
        'tt_content',
        'sys_template',
        'sys_file_reference',
    ];
}
