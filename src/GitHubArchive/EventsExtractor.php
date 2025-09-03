<?php

namespace App\GitHubArchive;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class EventsExtractor
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?Filesystem $fs = new Filesystem(),
    ) {
    }

    public function extract(string $path): string
    {
        if (!$this->fs->exists($path)) {
            throw new \RuntimeException(sprintf('[Extractor] File "%s" does not exist.', $path));
        }

        $destPath = Path::join(Path::getDirectory($path), 'processing', Path::getFilenameWithoutExtension($path));

        if ($this->fs->exists($destPath)) {
            $this->logger->info('[Extractor] Removing already existing file at "{dest}"...', ['dest' => $destPath]);
            $this->fs->remove($destPath);
        }

        $this->logger->info('[Extractor] Deflating "{path}" to "{dest}".', ['path' => $path, 'dest' => $destPath]);

        $srcHandler = gzopen($path, 'rb');

        if (!$srcHandler) {
            throw new \RuntimeException(sprintf('[Extractor] Cannot read file "%s".', $path));
        }

        while (!gzeof($srcHandler)) {
            $this->fs->appendToFile($destPath, gzread($srcHandler, 16));
        }

        gzclose($srcHandler);

        $this->logger->info('[Extractor] "{path}" successfully deflated to "{dest}".', ['path' => $path, 'dest' => $destPath]);

        return $destPath;
    }
}
