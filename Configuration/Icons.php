<?php

/*
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

// The Netresearch module group is shared: nr_textdb and universal_messenger
// register the same identifier, and the last extension loaded wins. All three
// ship ModuleGroup.svg with identical bytes. The module menu renders the icon
// inline, so its currentColor letter follows the backend colour scheme.
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
