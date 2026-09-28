<?php

declare(strict_types=1);

namespace WcoApi\Storage;

use WcoApi\Exception\WcoException;

final class LocalDirectoryStorage implements RecordingStorageInterface
{
    public function __construct(
        private readonly string $directory,
    ) {
    }

    public function write(string $recordingName, string $binary, string $extension = 'mp3'): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new WcoException('Nie można utworzyć katalogu storage: ' . $this->directory);
        }

        $path = $this->pathFor($recordingName, $extension);
        if (file_put_contents($path, $binary) === false) {
            throw new WcoException('Nie zapisano pliku: ' . $path);
        }

        return $path;
    }

    public function exists(string $recordingName, string $extension = 'mp3'): bool
    {
        $path = $this->pathFor($recordingName, $extension);

        return is_file($path) && filesize($path) > 0;
    }

    public function location(): string
    {
        return $this->directory;
    }

    private function pathFor(string $recordingName, string $extension): string
    {
        $safe = basename($recordingName);

        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $safe . '.' . ltrim($extension, '.');
    }
}
