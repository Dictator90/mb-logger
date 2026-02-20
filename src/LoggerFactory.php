<?php

declare(strict_types=1);

namespace MB\Logger;

use MB\Logger\Contracts\FormatterInterface;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;

final class LoggerFactory
{
    /**
     * Логгер с одним файлом (driver: single). По умолчанию app.log.
     */
    public static function single(
        string $basePath,
        string $filePattern = 'app.log',
        ?FormatterInterface $formatter = null
    ): Logger {
        $config = LoggerConfig::fromArray([
            'basePath' => $basePath,
            'defaultChannel' => 'app',
            'channels' => [
                'app' => [
                    'driver' => LoggerConfig::DRIVER_SINGLE,
                    'path' => '',
                    'filenamePattern' => $filePattern,
                ],
            ],
        ]);

        return Logger::fromConfig($config, $formatter, null);
    }

    /**
     * Логгер с ежедневной ротацией по дате (driver: daily). По умолчанию {date}.log.
     */
    public static function daily(
        string $basePath,
        string $filePattern = '{date}.log',
        ?FormatterInterface $formatter = null
    ): Logger {
        $config = LoggerConfig::fromArray([
            'basePath' => $basePath,
            'defaultChannel' => 'app',
            'channels' => [
                'app' => [
                    'driver' => LoggerConfig::DRIVER_DAILY,
                    'path' => '',
                    'filenamePattern' => $filePattern,
                    'dateFormat' => 'Y-m-d',
                ],
            ],
        ]);

        return Logger::fromConfig($config, $formatter, null);
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function fromArray(
        array $config,
        ?FormatterInterface $formatter = null
    ): Logger {
        $loggerConfig = LoggerConfig::fromArray($config);

        return Logger::fromConfig($loggerConfig, $formatter);
    }
}
