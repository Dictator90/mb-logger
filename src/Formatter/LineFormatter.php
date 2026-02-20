<?php

declare(strict_types=1);

namespace MB\Logger\Formatter;

use MB\Logger\Contracts\FormatterInterface;
use DateTimeImmutable;
use DateTimeZone;
use Stringable;

final class LineFormatter implements FormatterInterface
{
    private string $dateFormat;

    public function __construct(string $dateFormat = 'Y-m-d H:i:s')
    {
        $this->dateFormat = $dateFormat;
    }

    public function format(string $level, string $message, array $context = []): string
    {
        $dateTime = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $date = $dateTime->format($this->dateFormat);

        $interpolated = $this->interpolate($message, $context);
        $contextJson = $this->encodeContext($context);

        return sprintf('[%s][%s] %s %s', $date, strtoupper($level), $interpolated, $contextJson);
    }

    private function interpolate(string $message, array $context): string
    {
        if ($context === []) {
            return $message;
        }

        $replace = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    private function encodeContext(array $context): string
    {
        if ($context === []) {
            return '{}';
        }

        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = $this->stringify($value);
        }

        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'array';
        }

        if (is_object($value)) {
            return 'object(' . $value::class . ')';
        }

        return 'unknown';
    }
}

