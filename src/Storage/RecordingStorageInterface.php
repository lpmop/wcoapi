<?php

declare(strict_types=1);

namespace WcoApi\Storage;

/**
 * Abstrakcja zapisu binariów nagrań (katalog lokalny, przyszły S3, …).
 */
interface RecordingStorageInterface
{
    /**
     * Zapisuje plik. Zwraca URI/ścieżkę wynikową.
     *
     * @return string lokalna ścieżka lub URI storage
     */
    public function write(string $recordingName, string $binary, string $extension = 'mp3'): string;

    public function exists(string $recordingName, string $extension = 'mp3'): bool;

    /** Bazowy katalog / prefix (do wyświetlenia w CLI). */
    public function location(): string;
}
