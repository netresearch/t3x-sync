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

namespace Netresearch\Sync\Tests\Unit\ViewHelpers;

use Netresearch\Sync\ViewHelpers\TranslateViewHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Test case for TranslateViewHelper.
 *
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class TranslateViewHelperTest extends TestCase
{
    private mixed $backupLanguageService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupLanguageService = $GLOBALS['LANG'] ?? null;

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturn('Page {id} in {area}');
        $GLOBALS['LANG'] = $languageService;
    }

    protected function tearDown(): void
    {
        $GLOBALS['LANG'] = $this->backupLanguageService;

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function render(array $arguments): string
    {
        $viewHelper = new TranslateViewHelper();
        $viewHelper->setArguments($arguments);

        return $viewHelper->render();
    }

    #[Test]
    public function scalarDataReplacesThePlaceholders(): void
    {
        self::assertSame(
            'Page 42 in live',
            $this->render(['id' => 'label.page', 'data' => ['id' => 42, '{area}' => 'live']]),
        );
    }

    #[Test]
    public function dataValuesThatAreNoScalarLeaveTheirPlaceholder(): void
    {
        self::assertSame(
            'Page 42 in {area}',
            $this->render(['id' => 'label.page', 'data' => ['id' => 42, 'area' => ['live']]]),
        );
    }
}
