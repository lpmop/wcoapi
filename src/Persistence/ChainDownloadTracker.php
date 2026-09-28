<?php

declare(strict_types=1);

namespace WcoApi\Persistence;

/** OR wielu trackerów — has() jeśli którykolwiek ma; mark() na wszystkie. */
final class ChainDownloadTracker implements DownloadTrackerInterface
{
    /** @param list<DownloadTrackerInterface> $trackers */
    public function __construct(
        private readonly array $trackers,
    ) {
    }

    public function has(string $recordingName): bool
    {
        foreach ($this->trackers as $tracker) {
            if ($tracker->has($recordingName)) {
                return true;
            }
        }

        return false;
    }

    public function mark(string $recordingName, string $storageUri, int $bytes, array $meta = []): void
    {
        foreach ($this->trackers as $tracker) {
            $tracker->mark($recordingName, $storageUri, $bytes, $meta);
        }
    }
}
