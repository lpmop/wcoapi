<?php

declare(strict_types=1);

namespace WcoApi\Persistence;

/**
 * Tracking „już pobrane” — lokalny ledger i/lub baza.
 */
interface DownloadTrackerInterface
{
    public function has(string $recordingName): bool;

    /**
     * @param array<string, mixed> $meta
     */
    public function mark(string $recordingName, string $storageUri, int $bytes, array $meta = []): void;
}
