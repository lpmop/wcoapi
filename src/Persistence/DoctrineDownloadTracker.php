<?php

declare(strict_types=1);

namespace WcoApi\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use WcoApi\Entity\RecordingDownload;
use WcoApi\Repository\RecordingDownloadRepository;

final class DoctrineDownloadTracker implements DownloadTrackerInterface
{
    public function __construct(
        private readonly RecordingDownloadRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly string $storageDriver,
    ) {
    }

    public function has(string $recordingName): bool
    {
        return $this->repository->findOneByName($recordingName) !== null;
    }

    public function mark(string $recordingName, string $storageUri, int $bytes, array $meta = []): void
    {
        $row = $this->repository->findOneByName($recordingName) ?? new RecordingDownload();
        $row->setRecordingName($recordingName)
            ->setStorageUri($storageUri)
            ->setBytes($bytes)
            ->setStorageDriver($this->storageDriver)
            ->setArchivedBy(isset($meta['archivedBy']) ? (string) $meta['archivedBy'] : null)
            ->setMeta($meta === [] ? null : $meta);

        $this->em->persist($row);
        $this->em->flush();
    }
}
