<?php

declare(strict_types=1);

namespace WcoApi;

use WcoApi\Exception\AuthenticationException;

/**
 * Konfiguracja klienta z env / DI.
 */
final class WcoConfig
{
    public readonly string $recordingPassword;
    public readonly bool $verifySsl;

    public function __construct(
        public readonly string $vpbxNumber,
        public readonly string $username,
        public readonly string $password,
        string $recordingPassword = '',
        public readonly string $baseUrl = 'https://wco.orange.pl/admin_api',
        public readonly string $downloadDir = 'var/recordings',
        bool|string $verifySsl = true,
    ) {
        $this->recordingPassword = $recordingPassword !== '' ? $recordingPassword : $password;
        if (is_string($verifySsl)) {
            $verifySsl = !in_array(strtolower($verifySsl), ['0', 'false', 'no', 'off'], true);
        }
        $this->verifySsl = $verifySsl;
    }

    /**
     * @param array<string, string|null> $env
     */
    public static function fromEnv(array $env): self
    {
        $get = static fn (string $key, string $default = ''): string => trim((string) ($env[$key] ?? $default));

        return new self(
            vpbxNumber: $get('WCO_VPBX'),
            username: $get('WCO_USER'),
            password: $get('WCO_PASS'),
            recordingPassword: $get('WCO_REC_PASS'),
            baseUrl: $get('WCO_BASE_URL', 'https://wco.orange.pl/admin_api'),
            downloadDir: $get('WCO_DOWNLOAD_DIR', 'var/recordings'),
            verifySsl: $get('WCO_SSL_VERIFY', '1'),
        );
    }

    public function assertCredentials(): void
    {
        if ($this->vpbxNumber === '' || $this->username === '' || $this->password === '') {
            throw new AuthenticationException(
                'Uzupełnij WCO_VPBX, WCO_USER i WCO_PASS w .env / .env.local'
            );
        }
    }
}
