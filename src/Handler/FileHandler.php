<?php

declare(strict_types=1);

namespace MB\Logger\Handler;

use MB\Logger\Contracts\ChannelHandlerInterface;
use MB\Filesystem\Contracts\Filesystem as FilesystemContract;

final class FileHandler implements ChannelHandlerInterface
{
    public function __construct(
        private readonly FilesystemContract $filesystem
    ) {
    }

    public function write(string $relativePath, string $line): void
    {
        // Prefer the O(1), lock-protected append primitive (FILE_APPEND | LOCK_EX)
        // when the underlying filesystem provides it - this avoids rewriting the
        // whole log file on every line and is safe under concurrent writers.
        // Fall back to a read-modify-write cycle for contract implementations
        // that do not expose append().
        if (method_exists($this->filesystem, 'append')) {
            $this->filesystem->append($relativePath, $line);

            return;
        }

        $this->filesystem->updateContent(
            $relativePath,
            static fn (string $current): string => $current . $line,
            false
        );
    }
}
