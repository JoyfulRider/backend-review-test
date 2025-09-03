<?php

namespace App\GitHubArchive;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventsDownloader
{
    private string $downloadPath;

    public function __construct(
        private readonly HttpClientInterface $gharchiveClient,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
        private readonly ?Filesystem $fs = new Filesystem(),
    ) {
        $this->downloadPath = Path::canonicalize(Path::join($projectDir, '/data/events'));
    }

    /**
     * @param \DateTimeImmutable $date Date to download archives from
     *
     * @return string[] List of downloaded file paths
     *
     * @throws TransportExceptionInterface
     */
    public function download(\DateTimeImmutable $date): array
    {
        $this->logger->info('[Downloader] Fetching archives from "{date}".', ['date' => $date->format('Y-m-d')]);

        $fullPath = Path::join($this->downloadPath, $date->format('Y'), $date->format('m'), $date->format('d'));
        $range = range(0, 23);

        // Restrict the hours range to those up to now if the requested date is today
        $now = new \DateTimeImmutable();
        if ($now->format('Ymd') === $date->format('Ymd')) {
            $range = range(0, ((int) $now->format('H')) - 1);
        }

        /** @var string[] $paths */
        $paths = [];

        foreach ($range as $hour) {
            $filename = sprintf('%s-%s.json.gz', $date->format('Y-m-d'), $hour);
            $filePath = Path::join($fullPath, $filename);

            if ($this->fs->exists($filePath)) {
                $this->logger->info('[Downloader] File "{filename}" ({fileSize}) already exists at: "{filePath}".', ['filename' => $filename, 'fileSize' => FormatterHelper::formatMemory(filesize($filePath)), 'filePath' => $filePath]);
                $paths[] = $filePath;

                continue;
            }

            $response = $this->gharchiveClient->request('GET', sprintf('/%s', $filename));
            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                $this->logger->error('[Downloader] Invalid status code returned for "{filename}": {statusCode}.', ['filename' => $filename, 'statusCode' => $statusCode]);

                continue;
            }

            foreach ($this->gharchiveClient->stream($response) as $chunk) {
                $this->fs->appendToFile($filePath, $chunk->getContent());
            }

            $this->logger->info('[Downloader] File "{filename}" ({fileSize}) successfully downloaded to: "{filePath}".', ['filename' => $filename, 'fileSize' => FormatterHelper::formatMemory(filesize($filePath)), 'filePath' => $filePath]);
            $paths[] = $filePath;
        }

        $this->logger->info('[Downloader] {archiveCount} archives found for "{date}".', ['archiveCount' => count($paths), 'date' => $date->format('Y-m-d')]);

        return $paths;
    }
}
