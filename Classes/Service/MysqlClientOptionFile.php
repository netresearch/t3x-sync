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

namespace Netresearch\Sync\Service;

use function chmod;
use function file_put_contents;
use function is_file;

use RuntimeException;

use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Passes the database credentials to the mysql and mysqldump clients in an option file.
 *
 * The clients read the file given with --defaults-extra-file, which must be the first option on the command
 * line. The file is readable only by the owner and is removed after the client has run, so the password
 * appears neither on the command line nor in the environment of the client process.
 *
 * @author  Netresearch DTT GmbH
 * @license GPL-3.0-or-later
 *
 * @see    https://dev.mysql.com/doc/refman/8.4/en/option-files.html
 */
final class MysqlClientOptionFile
{
    /**
     * Connection parameters written to the [client] group, as Doctrine DBAL parameter => client option.
     *
     * @var array<string, string>
     */
    private const OPTIONS = [
        'host'        => 'host',
        'port'        => 'port',
        'unix_socket' => 'socket',
        'user'        => 'user',
        'password'    => 'password',
    ];

    /**
     * Runs $callback with the command line option that points the client to a temporary option file holding
     * the given connection parameters. The option file is removed afterwards, also if $callback throws.
     *
     * @template T
     *
     * @param array<string, mixed>                $connectionParams Doctrine DBAL connection parameters
     * @param callable(string $defaultsOption): T $callback         Receives "--defaults-extra-file=<escaped path>"
     *
     * @return T
     */
    public static function run(array $connectionParams, callable $callback): mixed
    {
        $file = self::create($connectionParams);

        try {
            return $callback('--defaults-extra-file=' . escapeshellarg($file));
        } finally {
            if (is_file($file)) {
                // nosemgrep: php.lang.security.unlink-use.unlink-use -- $file is the return value of tempnam() in create(); no request data reaches it
                unlink($file);
            }
        }
    }

    /**
     * Returns the content of the option file for the given connection parameters.
     *
     * @param array<string, mixed> $connectionParams Doctrine DBAL connection parameters
     *
     * @return string
     */
    public static function render(array $connectionParams): string
    {
        $content = "[client]\n";

        foreach (self::OPTIONS as $parameter => $option) {
            $value = $connectionParams[$parameter] ?? null;

            if (!is_scalar($value) || ((string) $value === '')) {
                continue;
            }

            $content .= sprintf("%s=\"%s\"\n", $option, self::escape((string) $value));
        }

        return $content;
    }

    /**
     * Creates the option file with mode 0600 in the system temporary directory and returns its path.
     *
     * @param array<string, mixed> $connectionParams
     *
     * @return string
     */
    private static function create(array $connectionParams): string
    {
        $file = tempnam(sys_get_temp_dir(), 'nrsync_my_');

        if ($file === false) {
            throw new RuntimeException('Failed to create the option file for the MySQL client');
        }

        if (!chmod($file, 0600)
            || (file_put_contents($file, self::render($connectionParams)) === false)
        ) {
            // nosemgrep: php.lang.security.unlink-use.unlink-use -- $file is the return value of tempnam() above
            unlink($file);

            throw new RuntimeException('Failed to write the option file for the MySQL client');
        }

        return $file;
    }

    /**
     * Escapes a value for a double-quoted option file value: the clients read "\\" as a backslash and "\""
     * as a double quote; line breaks and tabs are written as escape sequences.
     *
     * @param string $value
     *
     * @return string
     */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $value,
        );
    }
}
