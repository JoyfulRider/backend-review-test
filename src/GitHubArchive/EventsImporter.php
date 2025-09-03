<?php

namespace App\GitHubArchive;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\SerializerInterface;

class EventsImporter
{
    public function __construct(
        private readonly EventMapper $eventMapper,
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ?Filesystem $fs = new Filesystem(),
    ) {
    }

    public function import(
        string $path,
        ?callable $onStart = null,
        ?callable $onItem = null,
        ?callable $onFinish = null,
        int $batchSize = 500,
    ): void {
        if (!$this->fs->exists($path)) {
            throw new \InvalidArgumentException(sprintf('[Importer] File "%s" does not exist.', $path));
        }

        $file = new \SplFileObject($path);
        $file->seek(PHP_INT_MAX);
        $lineCount = $file->key();

        $this->logger->info('[Importer] {lineCount} lines to process from "{file}" in bulks of {batchSize} events.', ['lineCount' => $lineCount, 'file' => $path, 'batchSize' => $batchSize]);

        if (null !== $onStart) {
            $onStart($lineCount);
        }

        $file->rewind();
        $i = 0;

        while ($file->valid()) {
            $line = $file->fgets();

            if ($file->eof()) {
                break;
            }

            $gitHubArchiveEvent = $this->serializer->deserialize($line, GitHubArchiveEvent::class, 'json');
            $event = $this->eventMapper->map($gitHubArchiveEvent);

            if (null !== $onItem) {
                $onItem();
            }

            if (null === $event) {
                continue;
            }

            $this->entityManager->persist($event);

            if (0 === ++$i % $batchSize) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        if (null !== $onFinish) {
            $onFinish();
        }

        $this->logger->info('[Importer] {count} events successfully imported from "{file}".', ['count' => $i, 'file' => $path]);

        $this->logger->info('[Importer] Removing "{file}"...', ['file' => $path]);
        $this->fs->remove($path);
    }
}
