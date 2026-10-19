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

namespace Netresearch\Sync\Tests\Unit\Traits\Fixtures;

use Netresearch\Sync\Traits\FlashMessageTrait;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Records the flash messages that FlashMessageTrait creates instead of queueing them.
 */
final class FlashMessageSubject
{
    use FlashMessageTrait;

    /**
     * @var list<array{string, ContextualFeedbackSeverity}>
     */
    public array $messages = [];

    public function createFlashMessage(
        string $message,
        string $title = '',
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::INFO,
    ): void {
        $this->messages[] = [$message, $severity];
    }
}
