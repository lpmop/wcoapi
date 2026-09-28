<?php

declare(strict_types=1);

namespace WcoApi\Storage;

use Doctrine\ORM\EntityManagerInterface;
use WcoApi\DownloadLedger;
use WcoApi\Persistence\ChainDownloadTracker;
use WcoApi\Persistence\DoctrineDownloadTracker;
use WcoApi\Persistence\DownloadTrackerInterface;
use WcoApi\Persistence\LedgerDownloadTracker;
use WcoApi\Persistence\NullDownloadTracker;
use WcoApi\Repository\RecordingDownloadRepository;

final class WcoStorageFactory
{
    private readonly bool $dbTrack;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $driver,
        private readonly string $path,
        bool|string $dbTrack,
        private readonly ?RecordingDownloadRepository $downloadRepository = null,
        private readonly ?EntityManagerInterface $em = null,
    ) {
        if (is_string($dbTrack)) {
            $dbTrack = !in_array(strtolower($dbTrack), ['0', 'false', 'no', 'off', ''], true);
        }
        $this->dbTrack = $dbTrack;
    }

    public function createStorage(): RecordingStorageInterface
    {
        $dir = $this->resolvePath($this->path);

        return match (strtolower($this->driver)) {
            'null', 'none', 'off' => new NullRecordingStorage(),
            'local', 'filesystem', 'dir', 'directory' => new LocalDirectoryStorage($dir),
            default => throw new \InvalidArgumentException(
                'Nieznany WCO_STORAGE_DRIVER=' . $this->driver . ' (obsługiwane: local, null)'
            ),
        };
    }

    public function createTracker(RecordingStorageInterface $storage): DownloadTrackerInterface
    {
        $trackers = [];

        if ($storage instanceof LocalDirectoryStorage) {
            $trackers[] = new LedgerDownloadTracker(new DownloadLedger($storage->location()));
        }

        if ($this->dbTrack) {
            if ($this->downloadRepository === null || $this->em === null) {
                // Brak EM/repo (np. przed wco:db-install) — działaj na ledgerze lokalnym
                if ($trackers === []) {
                    return new NullDownloadTracker();
                }

                return count($trackers) === 1 ? $trackers[0] : new ChainDownloadTracker($trackers);
            }
            $trackers[] = new DoctrineDownloadTracker($this->downloadRepository, $this->em, $this->driver);
        }

        if ($trackers === []) {
            return new NullDownloadTracker();
        }

        return count($trackers) === 1 ? $trackers[0] : new ChainDownloadTracker($trackers);
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            return $path;
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
