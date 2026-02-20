<?php

declare(strict_types=1);

namespace MB\Logger;

final class LoggerConfig
{
    public const DRIVER_SINGLE = 'single';

    public const DRIVER_DAILY = 'daily';

    public string $basePath;

    public string $defaultChannel;

    /**
     * @var array<string,array{driver:string,path:string,filenamePattern:string,dateFormat:string,levels:?array<string>}>
     */
    public array $channels;

    /**
     * @param array<string,array{driver?:string,path?:string,filenamePattern?:string,dateFormat?:string,levels?:?array<string>}> $channels
     */
    public function __construct(string $basePath, string $defaultChannel, array $channels)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->defaultChannel = $defaultChannel;
        $this->channels = $channels;
    }

    public static function singleFile(string $basePath, string $filename = 'app.log', string $channel = 'app'): self
    {
        $channels = [
            $channel => [
                'driver' => self::DRIVER_SINGLE,
                'path' => '',
                'filenamePattern' => $filename,
                'dateFormat' => 'Y-m-d',
                'levels' => null,
            ],
        ];

        return new self($basePath, $channel, $channels);
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $basePath = $config['basePath'] ?? '';
        $defaultChannel = $config['defaultChannel'] ?? 'app';
        $channels = [];
        $channelsConfig = $config['channels'] ?? [];

        foreach ($channelsConfig as $name => $channelConfig) {
            $channels[$name] = self::normalizeChannelArray($name, is_array($channelConfig) ? $channelConfig : []);
        }

        if (!isset($channels[$defaultChannel])) {
            $channels[$defaultChannel] = self::defaultChannelArray($defaultChannel, '');
        }

        return new self($basePath, $defaultChannel, $channels);
    }

    /**
     * @return array{driver:string,path:string,filenamePattern:string,dateFormat:string,levels:?array<string>}
     */
    public function getChannel(string $name): array
    {
        if (!isset($this->channels[$name])) {
            $this->channels[$name] = self::defaultChannelArray($name, $name);
        }

        return $this->channels[$name];
    }

    public function resolveFilename(string $name, string $level, \DateTimeInterface $date): string
    {
        $ch = $this->getChannel($name);
        $replacements = [
            '{level}' => $level,
            '{LEVEL}' => strtoupper($level),
            '{date}' => $date->format($ch['dateFormat']),
        ];

        return strtr($ch['filenamePattern'], $replacements);
    }

    public function getChannelRelativePath(string $name, string $level, \DateTimeInterface $date): string
    {
        $ch = $this->getChannel($name);
        $fileName = $this->resolveFilename($name, $level, $date);
        $path = trim($ch['path'], DIRECTORY_SEPARATOR);

        return $path === '' ? $fileName : $path . DIRECTORY_SEPARATOR . $fileName;
    }

    public function acceptsLevel(string $name, string $level): bool
    {
        $ch = $this->getChannel($name);
        $levels = $ch['levels'];

        if ($levels === null) {
            return true;
        }

        return in_array($level, $levels, true);
    }

    /**
     * @return array{driver:string,path:string,filenamePattern:string,dateFormat:string,levels:?array<string>}
     */
    private static function defaultChannelArray(string $name, string $path): array
    {
        $path = $path !== '' ? trim($path, DIRECTORY_SEPARATOR) : $name;

        return [
            'driver' => self::DRIVER_SINGLE,
            'path' => $path,
            'filenamePattern' => 'app.log',
            'dateFormat' => 'Y-m-d',
            'levels' => null,
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array{driver:string,path:string,filenamePattern:string,dateFormat:string,levels:?array<string>}
     */
    private static function normalizeChannelArray(string $name, array $config): array
    {
        $driver = $config['driver'] ?? self::DRIVER_SINGLE;
        $driver = $driver === self::DRIVER_DAILY ? self::DRIVER_DAILY : self::DRIVER_SINGLE;
        $path = isset($config['path']) ? trim((string) $config['path'], DIRECTORY_SEPARATOR) : $name;

        return [
            'driver' => $driver,
            'path' => $path,
            'filenamePattern' => $config['filenamePattern'] ?? 'app.log',
            'dateFormat' => $config['dateFormat'] ?? 'Y-m-d',
            'levels' => $config['levels'] ?? null,
        ];
    }
}
