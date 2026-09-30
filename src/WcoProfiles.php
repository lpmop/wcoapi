<?php

declare(strict_types=1);

namespace WcoApi;

use WcoApi\Exception\AuthenticationException;

final class WcoProfiles
{
    public function __construct(
        private readonly string $filePath,
        private readonly string $envDefaultProfile = '',
    ) {
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function load(): array
    {
        if (!is_file($this->filePath)) {
            return ['default' => null, 'profiles' => []];
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false || trim($raw) === '') {
            return ['default' => null, 'profiles' => []];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new AuthenticationException('Niepoprawny JSON w ' . $this->filePath . ': ' . $e->getMessage());
        }

        if (!is_array($data)) {
            return ['default' => null, 'profiles' => []];
        }

        $profiles = [];
        foreach (($data['profiles'] ?? []) as $name => $p) {
            if (!is_string($name) || !is_array($p)) {
                continue;
            }
            $profiles[$name] = [
                'vpbx' => trim((string) ($p['vpbx'] ?? '')),
                'user' => trim((string) ($p['user'] ?? '')),
                'pass' => (string) ($p['pass'] ?? ''),
                'rec_pass' => (string) ($p['rec_pass'] ?? ''),
                'label' => isset($p['label']) ? trim((string) $p['label']) : '',
            ];
        }

        $default = $data['default'] ?? null;
        $default = is_string($default) && $default !== '' ? $default : null;

        return ['default' => $default, 'profiles' => $profiles];
    }

    public function save(array $data): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new AuthenticationException('Nie można utworzyć katalogu: ' . $dir);
        }

        $payload = [
            'default' => $data['default'] ?? null,
            'profiles' => $data['profiles'] ?? [],
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($this->filePath, $json) === false) {
            throw new AuthenticationException('Nie zapisano profili: ' . $this->filePath);
        }
    }

    public function names(): array
    {
        return array_keys($this->load()['profiles']);
    }

    public function defaultName(): ?string
    {
        $data = $this->load();
        if ($this->envDefaultProfile !== '' && isset($data['profiles'][$this->envDefaultProfile])) {
            return $this->envDefaultProfile;
        }
        if ($data['default'] !== null && isset($data['profiles'][$data['default']])) {
            return $data['default'];
        }
        $names = array_keys($data['profiles']);

        return $names[0] ?? null;
    }

    public function setDefault(string $name): void
    {
        $data = $this->load();
        if (!isset($data['profiles'][$name])) {
            throw new AuthenticationException(sprintf('Brak profilu "%s". Dostępne: %s', $name, implode(', ', array_keys($data['profiles'])) ?: '(brak)'));
        }
        $data['default'] = $name;
        $this->save($data);
    }

    public function upsert(string $name, array $profile, bool $makeDefault = false): void
    {
        $name = trim($name);
        if ($name === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new AuthenticationException('Nazwa profilu: litery, cyfry, . _ -');
        }

        $data = $this->load();
        $data['profiles'][$name] = [
            'vpbx' => trim($profile['vpbx']),
            'user' => trim($profile['user']),
            'pass' => $profile['pass'],
            'rec_pass' => $profile['rec_pass'] ?? '',
            'label' => trim($profile['label'] ?? ''),
        ];
        if ($makeDefault || $data['default'] === null) {
            $data['default'] = $name;
        }
        $this->save($data);
    }

    public function remove(string $name): void
    {
        $data = $this->load();
        if (!isset($data['profiles'][$name])) {
            throw new AuthenticationException(sprintf('Brak profilu "%s"', $name));
        }
        unset($data['profiles'][$name]);
        if ($data['default'] === $name) {
            $names = array_keys($data['profiles']);
            $data['default'] = $names[0] ?? null;
        }
        $this->save($data);
    }

    public function resolve(?string $profileName, WcoConfig $baseFromEnv): WcoConfig
    {
        $data = $this->load();
        $name = $profileName;
        if ($name === null || $name === '') {
            $name = $this->defaultName();
        }

        if ($name === null || $name === '') {
            return $baseFromEnv;
        }

        if (!isset($data['profiles'][$name])) {
            throw new AuthenticationException(sprintf(
                'Nieznany profil "%s". Dostępne: %s (lub dodaj: php bin/console wco:profiles:add)',
                $name,
                implode(', ', array_keys($data['profiles'])) ?: '(brak)'
            ));
        }

        $p = $data['profiles'][$name];

        return new WcoConfig(
            vpbxNumber: $p['vpbx'] !== '' ? $p['vpbx'] : $baseFromEnv->vpbxNumber,
            username: $p['user'],
            password: $p['pass'],
            recordingPassword: $p['rec_pass'] !== '' ? $p['rec_pass'] : $p['pass'],
            baseUrl: $baseFromEnv->baseUrl,
            downloadDir: $baseFromEnv->downloadDir,
            verifySsl: $baseFromEnv->verifySsl,
        );
    }

    public function activeLabel(?string $profileName): string
    {
        $data = $this->load();
        $name = $profileName !== null && $profileName !== '' ? $profileName : $this->defaultName();
        if ($name === null) {
            return 'env';
        }
        $label = $data['profiles'][$name]['label'] ?? '';

        return $label !== '' ? sprintf('%s (%s)', $name, $label) : $name;
    }
}
