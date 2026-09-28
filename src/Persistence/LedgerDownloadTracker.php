<?php

declare(strict_types=1);

namespace WcoApi\Persistence;

use WcoApi\DownloadLedger;

/** Tracker oparty o lokalny JSON + obecność pliku MP3. */
final class LedgerDownloadTracker implements DownloadTrackerInterface
{
    public function __construct(
        private readonly DownloadLedger $ledger,
    ) {
    }

    public function has(string $recordingName): bool
    {
        return $this->ledger->has($recordingName);
    }

    public function mark(string $recordingName, string $storageUri, int $bytes, array $meta = []): void
    {
        $this->ledger->mark($recordingName, $storageUri);
    }
}
