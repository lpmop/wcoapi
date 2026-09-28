<?php

declare(strict_types=1);

namespace WcoApi\Persistence;

/** Wyłączony tracking — has() zawsze false. */
final class NullDownloadTracker implements DownloadTrackerInterface
{
    public function has(string $recordingName): bool
    {
        return false;
    }

    public function mark(string $recordingName, string $storageUri, int $bytes, array $meta = []): void
    {
        // no-op
    }
}
