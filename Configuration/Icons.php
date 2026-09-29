<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

// The Netresearch module group is shared: on 13.4, nr_textdb 3.x and
// universal_messenger 2.x register the same identifier, and the extension
// loaded last wins; Composer loads both after nr_sync. nr_textdb ships
// ModuleGroup.svg with identical bytes from its TYPO3_13 branch on. The
// module menu renders the icon inline, so its currentColor letter follows
// the backend colour scheme.
return [
    'extension-netresearch-module' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_sync/Resources/Public/Icons/ModuleGroup.svg',
    ],
    'extension-netresearch-sync' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_sync/Resources/Public/Icons/Extension.svg',
    ],
];
