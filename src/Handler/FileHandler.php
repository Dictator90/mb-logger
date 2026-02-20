<?php

declare(strict_types=1);

namespace MB\Logger\Handler;

use MB\Logger\Contracts\ChannelHandlerInterface;
use MB\Filesystem\Filesystem as MbFilesystem;

final class FileHandler implements ChannelHandlerInterface
{
    public function __construct(
        private readonly MbFilesystem $filesystem
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
