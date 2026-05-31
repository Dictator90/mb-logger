<?php

declare(strict_types=1);

namespace MB\Logger;

use MB\Logger\Contracts\FormatterInterface;
use MB\Logger\Formatter\LineFormatter;
use MB\Logger\Handler\FileHandler;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;
use MB\Filesystem\Filesystem;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Stringable;

class Logger extends AbstractLogger
{
    private LoggerConfig $config;

    private FormatterInterface $formatter;

    private FilesystemContract $filesystem;

    /**
     * @var string[]
     */
    private array $levels = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * @var array<string,LoggerInterface>
     */
    private array $channels = [];

    private FileHandler $fileHandler;

    public function __construct(string $basePath, ?FormatterInterface $formatter = null, ?FilesystemContract $filesystem = null)
    {
        $this->config = LoggerConfig::singleFile($basePath, 'app.log', 'app');
        $this->formatter = $formatter ?? new LineFormatter();
        $this->filesystem = $filesystem ?? new Filesystem($this->config->basePath);
        $this->filesystem->makeDirectory($this->config->basePath, 0755, true);
        $this->fileHandler = new FileHandler($this->filesystem);
    }

    public static function create(
        string $basePath,
        string $filePattern = 'app.log',
        ?FormatterInterface $formatter = null,
        ?FilesystemContract $filesystem = null
    ): self {
        $config = LoggerConfig::fromArray([
            'basePath' => $basePath,
            'defaultChannel' => 'app',
            'channels' => [
                'app' => [
                    'driver' => self::detectDriverByPattern($filePattern),
                    'path' => '',
                    'filenamePattern' => $filePattern,
                    'dateFormat' => 'Y-m-d',
                ],
            ],
        ]);

        return self::fromConfig($config, $formatter, $filesystem);
    }

    public static function fromConfig(LoggerConfig $config, ?FormatterInterface $formatter = null, ?FilesystemContract $filesystem = null): self
    {
        $logger = new self($config->basePath, $formatter, $filesystem);
        $logger->config = $config;

        return $logger;
    }

    public function channel(string $name): LoggerInterface
    {
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        $this->channels[$name] = new ChannelProxy($this, $name);

        return $this->channels[$name];
    }

    public function logToChannel(string $channelName, string $level, string|Stringable $message, array $context = []): void
    {
        $level = strtolower((string) $level);

        if (!in_array($level, $this->levels, true)) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }

        if (!$this->config->acceptsLevel($channelName, $level)) {
            return;
        }

        $relativePath = $this->config->getChannelRelativePath($channelName, $level, new \DateTimeImmutable());
        $line = $this->formatter->format($level, (string) $message, $context) . PHP_EOL;
        $this->fileHandler->write($relativePath, $line);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $level = strtolower((string) $level);

        if (!in_array($level, $this->levels, true)) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }

        $this->channel($this->resolveChannelForLevel($level))->log($level, $message, $context);
    }

    /**
     * Resolve which channel a level should be routed to.
     *
     * A channel that declares an explicit `levels` whitelist and accepts the
     * level takes precedence (most specific wins); otherwise the message goes
     * to the default channel.
     */
    private function resolveChannelForLevel(string $level): string
    {
        foreach ($this->config->channels as $name => $ch) {
            if ($ch['levels'] !== null && $this->config->acceptsLevel($name, $level)) {
                return $name;
            }
        }

        return $this->config->defaultChannel;
    }

    private static function detectDriverByPattern(string $filePattern): string
    {
        return str_contains($filePattern, '{date}')
            ? LoggerConfig::DRIVER_DAILY
            : LoggerConfig::DRIVER_SINGLE;
    }
}

