<?php

declare(strict_types=1);

namespace WcoApi;

/**
 * Lokalny rejestr pobranych nagrań (API WCO nie ma „pobrane przez użytkownika X”).
 */
final class DownloadLedger
{
    public function __construct(
        private readonly string $directory,
        private readonly string $filename = '.wco-downloaded.json',
    ) {
    }

    public function path(): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $this->filename;
    }

    /** @return list<string> */
    public function names(): array
    {
        $file = $this->path();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return [];
        }
        $names = $data['names'] ?? [];

        return array_values(array_unique(array_map('strval', is_array($names) ? $names : [])));
    }

    public function has(string $name): bool
    {
        return in_array($name, $this->names(), true) || $this->mp3Exists($name);
    }

    public function mp3Exists(string $name): bool
    {
        $mp3 = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $name . '.mp3';

        return is_file($mp3) && filesize($mp3) > 0;
    }

    public function mark(string $name, ?string $savedPath = null): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            return;
        }

        $names = $this->names();
        if (!in_array($name, $names, true)) {
            $names[] = $name;
        }

        $payload = [
            'updatedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Warsaw')))->format(DATE_ATOM),
            'names' => array_values($names),
            'last' => [
                'name' => $name,
                'path' => $savedPath,
            ],
        ];

        file_put_contents(
            $this->path(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }
}
