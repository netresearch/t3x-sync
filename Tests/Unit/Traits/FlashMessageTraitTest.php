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

namespace Netresearch\Sync\Tests\Unit\Traits;

use Netresearch\Sync\Tests\Unit\Traits\Fixtures\FlashMessageSubject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use ValueError;

/**
 * Test case for FlashMessageTrait.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class FlashMessageTraitTest extends TestCase
{
    #[Test]
    public function addMessageAcceptsTheSeverityAsEnum(): void
    {
        $subject = new FlashMessageSubject();
        $subject->addMessage('text', ContextualFeedbackSeverity::WARNING);

        self::assertSame([['text', ContextualFeedbackSeverity::WARNING]], $subject->messages);
    }

    #[Test]
    public function addMessageAcceptsTheSeverityAsInteger(): void
    {
        $subject = new FlashMessageSubject();
        $subject->addMessage('text', ContextualFeedbackSeverity::ERROR->value);

        self::assertSame([['text', ContextualFeedbackSeverity::ERROR]], $subject->messages);
    }

    #[Test]
    public function addMessageRejectsAnUnknownSeverity(): void
    {
        $this->expectException(ValueError::class);

        (new FlashMessageSubject())->addMessage('text', 99);
    }
}
