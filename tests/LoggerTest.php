<?php

declare(strict_types=1);

namespace MB\Logger\Tests;

use MB\Logger\Logger;
use MB\Logger\LoggerConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class LoggerTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mb_logger_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->basePath);

        parent::tearDown();
    }

    public function testBaseDirectoryIsCreated(): void
    {
        $this->assertDirectoryDoesNotExist($this->basePath);

        new Logger($this->basePath);

        $this->assertDirectoryExists($this->basePath);
    }

    public function testInfoCreatesLogFileWithMessage(): void
    {
        $logger = new Logger($this->basePath);

        $logger->info('Test message');

        $logFile = $this->basePath . DIRECTORY_SEPARATOR . 'app.log';

        $this->assertFileExists($logFile);

        $contents = file_get_contents($logFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('Test message', $contents);
        $this->assertStringContainsString('[INFO]', $contents);
    }

    public function testContextIsSerializedIntoLogLine(): void
    {
        $logger = new Logger($this->basePath);

        $logger->info('User logged in', ['user_id' => 123, 'role' => 'admin']);

        $logFile = $this->basePath . DIRECTORY_SEPARATOR . 'app.log';

        $this->assertFileExists($logFile);

        $contents = file_get_contents($logFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('User logged in', $contents);
        $this->assertStringContainsString('"user_id":"123"', $contents);
        $this->assertStringContainsString('"role":"admin"', $contents);
    }

    public function testInvalidLevelThrowsPsrInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $logger = new Logger($this->basePath);
        $logger->log('invalid-level', 'Message');
    }

    public function testAllPsrLevelsAreAccepted(): void
    {
        $logger = new Logger($this->basePath);

        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        foreach ($levels as $level) {
            $logger->log($level, 'Level test: ' . $level);
        }

        $logFile = $this->basePath . DIRECTORY_SEPARATOR . 'app.log';

        $this->assertFileExists($logFile);

        $contents = file_get_contents($logFile);

        $this->assertIsString($contents);

        foreach ($levels as $level) {
            $this->assertStringContainsString('Level test: ' . $level, $contents);
        }
    }

    public function testLoggerRoutesLevelToMatchingChannel(): void
    {
        $config = LoggerConfig::fromArray([
            'basePath' => $this->basePath,
            'defaultChannel' => 'app',
            'channels' => [
                'app' => [
                    'driver' => 'single',
                    'path' => '',
                    'filenamePattern' => 'app.log',
                ],
                'error' => [
                    'driver' => 'single',
                    'path' => 'errors',
                    'filenamePattern' => 'error.log',
                    'levels' => [LogLevel::ERROR],
                ],
            ],
        ]);

        $logger = Logger::fromConfig($config);
        $logger->info('Info message');
        $logger->error('Error message');

        $appLog = $this->basePath . DIRECTORY_SEPARATOR . 'app.log';
        $errorLog = $this->basePath . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'error.log';

        $this->assertFileExists($appLog);
        $this->assertFileExists($errorLog);

        $appContents = file_get_contents($appLog);
        $errorContents = file_get_contents($errorLog);

        $this->assertIsString($appContents);
        $this->assertIsString($errorContents);

        $this->assertStringContainsString('Info message', $appContents);
        $this->assertStringNotContainsString('Error message', $appContents);
        $this->assertStringContainsString('Error message', $errorContents);
    }

    public function testCreateWritesToDefaultAppLog(): void
    {
        $logger = Logger::create($this->basePath);
        $logger->info('Quick start message');

        $logFile = $this->basePath . DIRECTORY_SEPARATOR . 'app.log';
        $this->assertFileExists($logFile);

        $contents = file_get_contents($logFile);
        $this->assertIsString($contents);
        $this->assertStringContainsString('Quick start message', $contents);
    }

    public function testCreateWithDatePatternUsesDailyFileName(): void
    {
        $logger = Logger::create($this->basePath, 'error_{date}.log');
        $logger->error('Daily quick start message');

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $logFile = $this->basePath . DIRECTORY_SEPARATOR . "error_{$today}.log";

        $this->assertFileExists($logFile);

        $contents = file_get_contents($logFile);
        $this->assertIsString($contents);
        $this->assertStringContainsString('Daily quick start message', $contents);
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

