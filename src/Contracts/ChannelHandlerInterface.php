<?php

declare(strict_types=1);

namespace MB\Logger\Contracts;

interface ChannelHandlerInterface
{
    public function write(string $relativePath, string $line): void;
}

