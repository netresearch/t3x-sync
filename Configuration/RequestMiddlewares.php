<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\Sync\Middleware\ClearCache;

return [
    'frontend' => [
        'nr/nr-sync/clear-cache' => [
            'target' => ClearCache::class,
            'before' => [
            ],
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
    ],
];
