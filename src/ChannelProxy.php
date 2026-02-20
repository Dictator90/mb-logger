<?php

declare(strict_types=1);

namespace MB\Logger;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Тонкий делегат для channel(): хранит ссылку на Logger и имя канала, делегирует log() в Logger::logToChannel().
 */
final class ChannelProxy extends AbstractLogger
{
    public function __construct(
        private readonly Logger $logger,
        private readonly string $channelName
    ) {
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logger->logToChannel($this->channelName, (string) $level, $message, $context);
    }
}
