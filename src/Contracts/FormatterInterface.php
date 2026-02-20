<?php

declare(strict_types=1);

namespace MB\Logger\Contracts;

interface FormatterInterface
{
    public function format(string $level, string $message, array $context = []): string;
}

