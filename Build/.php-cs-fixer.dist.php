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

$header = <<<'EOF'
    This file is part of the package netresearch/nr-sync.

    SPDX-License-Identifier: GPL-3.0-or-later
    SPDX-FileCopyrightText: Netresearch DTT GmbH

    For the full copyright and license information, please read the
    LICENSE file that was distributed with this source code.
    EOF;

$createConfig = require __DIR__ . '/../.build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/config.php';

return $createConfig($header, __DIR__ . '/..');
