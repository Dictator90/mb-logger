<?php

declare(strict_types=1);

namespace MB\Logger\Tests;

use MB\Logger\Logger;
use MB\Logger\LoggerConfig;
use MB\Logger\LoggerFactory;
use Psr\Log\LogLevel;
use PHPUnit\Framework\TestCase;

class ChannelLoggerTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_logger_channel_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);

        parent::tearDown();
    }

    public function testChannelCreatesSubdirectoryAndWritesLog(): void
    {
        $logger = new Logger($this->basePath);

        $channel = $logger->channel('payments');
        $channel->info('Payment processed');

        $channelDir = $this->basePath . DIRECTORY_SEPARATOR . 'payments';
        $channelLog = $channelDir . DIRECTORY_SEPARATOR . 'app.log';

        $this->assertDirectoryExists($channelDir);
        $this->assertFileExists($channelLog);

        $contents = file_get_contents($channelLog);

        $this->assertIsString($contents);
        $this->assertStringContainsString('Payment processed', $contents);
        $this->assertStringContainsString('[INFO]', $contents);
    }

    public function testDifferentChannelsWriteToDifferentFiles(): void
    {
        $logger = new Logger($this->basePath);

        $paymentsLogger = $logger->channel('payments');
        $ordersLogger = $logger->channel('orders');

        $paymentsLogger->info('Payment event');
        $ordersLogger->info('Order event');

        $paymentsLog = $this->basePath . DIRECTORY_SEPARATOR . 'payments' . DIRECTORY_SEPARATOR . 'app.log';
        $ordersLog = $this->basePath . DIRECTORY_SEPARATOR . 'orders' . DIRECTORY_SEPARATOR . 'app.log';

        $this->assertFileExists($paymentsLog);
        $this->assertFileExists($ordersLog);

        $paymentsContents = file_get_contents($paymentsLog);
        $ordersContents = file_get_contents($ordersLog);

        $this->assertIsString($paymentsContents);
        $this->assertIsString($ordersContents);

        $this->assertStringContainsString('Payment event', $paymentsContents);
        $this->assertStringNotContainsString('Order event', $paymentsContents);

        $this->assertStringContainsString('Order event', $ordersContents);
        $this->assertStringNotContainsString('Payment event', $ordersContents);
    }

    public function testCustomFilenamePatternWithDateAndLevel(): void
    {
        $config = LoggerConfig::fromArray([
            'basePath' => $this->basePath,
            'defaultChannel' => 'app',
            'channels' => [
                'error' => [
                    'driver' => 'single',
                    'path' => 'errors',
                    'filenamePattern' => 'error_{date}.log',
                    'dateFormat' => 'Y-m-d',
                    'levels' => [
                        LogLevel::ERROR,
                        LogLevel::CRITICAL,
                        LogLevel::ALERT,
                        LogLevel::EMERGENCY,
                    ],
                ],
            ],
        ]);

        $logger = Logger::fromConfig($config);

        $logger->error('Something failed');

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $expectedFile = $this->basePath . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . "error_{$today}.log";

        $this->assertFileExists($expectedFile);

        $contents = file_get_contents($expectedFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('Something failed', $contents);
        $this->assertStringContainsString('[ERROR]', $contents);
    }

    public function testLevelFilteredChannelDoesNotLogOtherLevels(): void
    {
        $config = LoggerConfig::fromArray([
            'basePath' => $this->basePath,
            'defaultChannel' => 'app',
            'channels' => [
                'error' => [
                    'driver' => 'single',
                    'path' => 'errors',
                    'filenamePattern' => '{level}_{date}.log',
                    'dateFormat' => 'Y-m-d',
                    'levels' => [
                        LogLevel::ERROR,
                    ],
                ],
            ],
        ]);

        $logger = Logger::fromConfig($config);

        $logger->info('Info message that should not go to error channel');
        $logger->error('Real error');

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $errorFile = $this->basePath . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . "error_{$today}.log";
        $infoFile = $this->basePath . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . "info_{$today}.log";

        $this->assertFileExists($errorFile);
        $this->assertFileDoesNotExist($infoFile);

        $contents = file_get_contents($errorFile);
        $this->assertIsString($contents);
        $this->assertStringContainsString('Real error', $contents);
        $this->assertStringNotContainsString('Info message that should not go to error channel', $contents);
    }

    public function testDailyDriverCreatesDatedFile(): void
    {
        $config = LoggerConfig::fromArray([
            'basePath' => $this->basePath,
            'defaultChannel' => 'daily',
            'channels' => [
                'daily' => [
                    'driver' => 'daily',
                    'path' => 'daily',
                    'filenamePattern' => '{date}.log',
                    'dateFormat' => 'Y-m-d',
                ],
            ],
        ]);

        $logger = Logger::fromConfig($config);
        $logger->info('Daily message');

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $dailyLog = $this->basePath . DIRECTORY_SEPARATOR . 'daily' . DIRECTORY_SEPARATOR . "{$today}.log";

        $this->assertFileExists($dailyLog);

        $contents = file_get_contents($dailyLog);
        $this->assertIsString($contents);
        $this->assertStringContainsString('Daily message', $contents);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}

