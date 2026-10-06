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

namespace Netresearch\Sync\Tests\Unit\Service;

use Netresearch\Sync\Service\MysqlClientOptionFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MysqlClientOptionFile::class)]
final class MysqlClientOptionFileTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function connectionParams(): array
    {
        return [
            'driver'   => 'mysqli',
            'host'     => 'db.example.org',
            'port'     => 3306,
            'user'     => 'typo3',
            'password' => 'p#ss "word" \\ with spaces',
            'dbname'   => 'typo3',
        ];
    }

    /**
     * Returns the path of the option file from "--defaults-extra-file='<path>'".
     */
    private function pathOf(string $defaultsOption): string
    {
        self::assertMatchesRegularExpression("/^--defaults-extra-file='[^']+'$/", $defaultsOption);

        return substr($defaultsOption, strlen("--defaults-extra-file='"), -1);
    }

    #[Test]
    public function credentialsAreWrittenToTheClientGroupOfTheOptionFile(): void
    {
        self::assertSame(
            "[client]\n"
            . "host=\"db.example.org\"\n"
            . "port=\"3306\"\n"
            . "user=\"typo3\"\n"
            . "password=\"p#ss \\\"word\\\" \\\\ with spaces\"\n",
            MysqlClientOptionFile::render($this->connectionParams()),
        );
    }

    #[Test]
    public function socketIsWrittenAndEmptyParametersAreLeftOut(): void
    {
        self::assertSame(
            "[client]\nsocket=\"/run/mysqld/mysqld.sock\"\nuser=\"typo3\"\n",
            MysqlClientOptionFile::render([
                'host'        => '',
                'unix_socket' => '/run/mysqld/mysqld.sock',
                'user'        => 'typo3',
                'password'    => '',
            ]),
        );
    }

    #[Test]
    public function lineBreaksInValuesAreEscaped(): void
    {
        self::assertSame(
            "[client]\npassword=\"a\\nb\\rc\\td\"\n",
            MysqlClientOptionFile::render(['password' => "a\nb\rc\td"]),
        );
    }

    #[Test]
    public function commandLineOptionNamesTheOptionFileButNotThePassword(): void
    {
        MysqlClientOptionFile::run(
            $this->connectionParams(),
            function (string $defaultsOption): void {
                self::assertStringNotContainsString('p#ss', $defaultsOption);
                self::assertSame(
                    MysqlClientOptionFile::render($this->connectionParams()),
                    file_get_contents($this->pathOf($defaultsOption)),
                );
            },
        );
    }

    #[Test]
    public function optionFileIsReadableOnlyByTheOwner(): void
    {
        MysqlClientOptionFile::run(
            $this->connectionParams(),
            function (string $defaultsOption): void {
                self::assertSame(0600, fileperms($this->pathOf($defaultsOption)) & 0777);
            },
        );
    }

    #[Test]
    public function optionFileIsRemovedAfterTheClientRan(): void
    {
        $path = MysqlClientOptionFile::run(
            $this->connectionParams(),
            fn (string $defaultsOption): string => $this->pathOf($defaultsOption),
        );

        self::assertFileDoesNotExist($path);
    }

    #[Test]
    public function optionFileIsRemovedWhenTheClientCallFails(): void
    {
        $path = '';

        try {
            MysqlClientOptionFile::run(
                $this->connectionParams(),
                function (string $defaultsOption) use (&$path): void {
                    $path = $this->pathOf($defaultsOption);

                    throw new RuntimeException('client failed', 1);
                },
            );
        } catch (RuntimeException) {
        }

        self::assertNotSame('', $path);
        self::assertFileDoesNotExist($path);
    }
}
