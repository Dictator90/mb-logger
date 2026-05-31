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
        $this->filesystem->updateContent(
            $relativePath,
            static fn (string $current): string => $current . $line,
            false
        );
    }
}
