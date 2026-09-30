<?php

declare(strict_types=1);

namespace WcoApi\Storage;

interface RecordingStorageInterface
{
    public function write(string $recordingName, string $binary, string $extension = 'mp3'): string;

    public function exists(string $recordingName, string $extension = 'mp3'): bool;

    public function location(): string;
}
