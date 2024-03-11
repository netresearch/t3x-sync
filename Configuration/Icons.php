<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tx-sync-module-web' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_sync/Resources/Public/Icons/Extension.svg',
    ],
];
