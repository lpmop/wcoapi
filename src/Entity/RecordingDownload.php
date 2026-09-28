<?php

declare(strict_types=1);

namespace WcoApi\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use WcoApi\Repository\RecordingDownloadRepository;

#[ORM\Entity(repositoryClass: RecordingDownloadRepository::class)]
#[ORM\Table(name: 'wco_recording_download')]
#[ORM\UniqueConstraint(name: 'uniq_recording_name', columns: ['recording_name'])]
class RecordingDownload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $recordingName = '';

    #[ORM\Column(length: 1024)]
    private string $storageUri = '';

    #[ORM\Column]
    private int $bytes = 0;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $archivedBy = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $storageDriver = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    #[ORM\Column]
    private \DateTimeImmutable $downloadedAt;

    public function __construct()
    {
        $this->downloadedAt = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Warsaw'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecordingName(): string
    {
        return $this->recordingName;
    }

    public function setRecordingName(string $recordingName): self
    {
        $this->recordingName = $recordingName;

        return $this;
    }

    public function getStorageUri(): string
    {
        return $this->storageUri;
    }

    public function setStorageUri(string $storageUri): self
    {
        $this->storageUri = $storageUri;

        return $this;
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    public function setBytes(int $bytes): self
    {
        $this->bytes = $bytes;

        return $this;
    }

    public function getArchivedBy(): ?string
    {
        return $this->archivedBy;
    }

    public function setArchivedBy(?string $archivedBy): self
    {
        $this->archivedBy = $archivedBy;

        return $this;
    }

    public function getStorageDriver(): ?string
    {
        return $this->storageDriver;
    }

    public function setStorageDriver(?string $storageDriver): self
    {
        $this->storageDriver = $storageDriver;

        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function getDownloadedAt(): \DateTimeImmutable
    {
        return $this->downloadedAt;
    }
}
