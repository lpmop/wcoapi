<?php

declare(strict_types=1);

namespace WcoApi\Storage;

final class NullRecordingStorage implements RecordingStorageInterface
{
    public function write(string $recordingName, string $binary, string $extension = 'mp3'): string
    {
        throw new \WcoApi\Exception\WcoException(
            'WCO_STORAGE_DRIVER=null — zapis plików wyłączony. Ustaw local (lub inny driver).'
        );
    }

    public function exists(string $recordingName, string $extension = 'mp3'): bool
    {
        return false;
    }

    public function location(): string
    {
        return '(disabled)';
    }
}
