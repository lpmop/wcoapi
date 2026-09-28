<?php

declare(strict_types=1);

namespace WcoApi\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use WcoApi\Entity\RecordingDownload;

/**
 * @extends ServiceEntityRepository<RecordingDownload>
 */
class RecordingDownloadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecordingDownload::class);
    }

    public function findOneByName(string $name): ?RecordingDownload
    {
        return $this->findOneBy(['recordingName' => $name]);
    }
}
