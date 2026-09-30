<?php

declare(strict_types=1);

namespace WcoApi\Persistence;

interface DownloadTrackerInterface
{
    public function has(string $recordingName): bool;

    public function mark(string $recordingName, string $storageUri, int $bytes, array $meta = []): void;
}
