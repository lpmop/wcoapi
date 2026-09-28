<?php

declare(strict_types=1);

namespace WcoApi;

use WcoApi\Exception\AuthenticationException;
use WcoApi\Exception\WcoException;
use ZipArchive;

/**
 * Klient nieoficjalnego API WCO (Orange) — prefiks /admin_api.
 *
 * @see docs/API.md
 */
final class WcoClient
{
    private const DEFAULT_BASE = 'https://wco.orange.pl/admin_api';

    private string $baseUrl;
    private CookieJar $cookies;
    private int $timeout;
    private bool $verifySsl;

    public function __construct(
        ?string $baseUrl = null,
        int $timeout = 60,
        bool $verifySsl = true,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE, '/');
        $this->cookies = new CookieJar();
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
    }

    /**
     * Logowanie jak w GUI: CSRF → perform_login.
     *
     * @return array<string, mixed> zdekodowane dane sesji z body perform_login
     */
    public function login(string $vpbxNumber, string $username, string $password, ?string $language = null): array
    {
        $this->request('GET', '/login');

        $xsrf = $this->cookies->get('XSRF-TOKEN');
        if ($xsrf === null || $xsrf === '') {
            throw new AuthenticationException('Brak XSRF-TOKEN po GET /login');
        }

        $response = $this->request('POST', '/perform_login', [
            'json' => [
                'vpbxNumber' => $vpbxNumber,
                'username' => $username,
                'password' => $password,
                'language' => $language,
            ],
            'csrf' => true,
        ]);

        if ($this->cookies->get('Authorization') === null && $this->cookies->get('JSESSIONID') === null) {
            throw new AuthenticationException('Logowanie nie ustawiło sesji (Authorization/JSESSIONID)');
        }

        return $this->decodeLoginBody($response['body']);
    }

    /** Odświeża JWT w cookie Authorization. */
    public function refreshJwt(): void
    {
        $this->request('POST', '/jwt-refresh', [
            'json' => new \stdClass(),
            'csrf' => true,
        ]);
    }

    /**
     * Lista nagrań (paginowana).
     *
     * @param array{
     *     dateFrom?: string,
     *     dateTo?: string,
     *     page?: int,
     *     size?: int,
     *     sort?: string,
     *     vrType?: string,
     *     archivedBy?: string|null,
     *     aPartyNumber?: string|null,
     *     bPartyNumber?: string|null,
     *     name?: string|null
     * } $params
     * @return array<string, mixed>
     */
    public function listRecordings(array $params = []): array
    {
        $tz = new \DateTimeZone('Europe/Warsaw');
        $now = new \DateTimeImmutable('now', $tz);
        $query = [
            'dateFrom' => $params['dateFrom'] ?? $now->modify('-60 days')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            'dateTo' => $params['dateTo'] ?? $now->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            'page' => (string) ($params['page'] ?? 0),
            'size' => (string) ($params['size'] ?? 10),
            'sort' => $params['sort'] ?? 'start,desc',
            'vrType' => $params['vrType'] ?? 'NWI',
        ];

        foreach (['aPartyNumber', 'bPartyNumber', 'name'] as $optional) {
            if (!empty($params[$optional])) {
                $query[$optional] = (string) $params[$optional];
            }
        }

        if (isset($params['archivedBy']) && $params['archivedBy'] !== null && $params['archivedBy'] !== '') {
            $query['archivedBy'] = (string) $this->normalizeArchivedBy($params['archivedBy']);
        }

        $response = $this->request('GET', '/recordings/', ['query' => $query]);
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new WcoException('Niepoprawna odpowiedź JSON z /recordings/');
        }

        return $data;
    }

    /**
     * Odblokowuje odsłuch nagrań hasłem szyfrowania.
     * W GUI często to to samo hasło co do logowania — zależy od konfiguracji VPBX.
     */
    public function unlockRecordingsWithPassword(string $password): bool
    {
        $response = $this->request('POST', '/encryption/by-key-pwd', [
            'json' => ['password' => $password],
            'csrf' => true,
        ]);

        $decoded = json_decode($response['body'], true);

        return $decoded === true;
    }

    public function isAuthenticationByKey(): bool
    {
        $response = $this->request('GET', '/encryption/is-authentication-by-key');
        $decoded = json_decode($response['body'], true);

        return $decoded === true;
    }

    /**
     * Pobiera ZIP z odszyfrowanymi nagraniami (.mp3 w środku).
     *
     * @param list<string> $recordingNames wartości pola `name` z listy nagrań
     */
    public function downloadRecordingsZip(array $recordingNames, bool $encrypted = false): string
    {
        if ($recordingNames === []) {
            throw new WcoException('Podaj co najmniej jedną nazwę nagrania');
        }

        $path = $encrypted ? '/recordings/encrypted/' : '/recordings/decrypted/';
        $response = $this->request('POST', $path, [
            'json' => array_values($recordingNames),
            'csrf' => true,
            'raw' => true,
        ]);

        if ($response['body'] === '') {
            throw new WcoException('Pusta odpowiedź z ' . $path);
        }

        return $response['body'];
    }

    /**
     * Pobiera odszyfrowane MP3 (endpoint decrypted-unzipped) — bez warstwy ZIP.
     *
     * @param list<string> $recordingNames
     */
    public function downloadDecryptedUnzipped(array $recordingNames): string
    {
        if ($recordingNames === []) {
            throw new WcoException('Podaj co najmniej jedną nazwę nagrania');
        }

        $response = $this->request('POST', '/recordings/decrypted-unzipped/', [
            'json' => array_values($recordingNames),
            'csrf' => true,
            'raw' => true,
        ]);

        if ($response['body'] === '') {
            throw new WcoException('Pusta odpowiedź z /recordings/decrypted-unzipped/');
        }

        return $response['body'];
    }

    /**
     * Pobiera nagranie i zapisuje lokalnie odszyfrowane .mp3.
     *
     * @param list<string> $recordingNames
     * @return list<string> ścieżki zapisanych plików
     */
    public function downloadAndExtractMp3(array $recordingNames, string $targetDirectory, bool $encrypted = false): array
    {
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new WcoException('Nie można utworzyć katalogu: ' . $targetDirectory);
        }

        if ($encrypted) {
            $zipBinary = $this->downloadRecordingsZip($recordingNames, true);
            $zipPath = $targetDirectory . DIRECTORY_SEPARATOR . 'wco-encrypted-' . bin2hex(random_bytes(4)) . '.zip';
            if (file_put_contents($zipPath, $zipBinary) === false) {
                throw new WcoException('Nie zapisano zaszyfrowanego ZIP: ' . $zipPath);
            }

            return [$zipPath];
        }

        // 1) Preferuj bezpośrednie odszyfrowane MP3 (jak GUI „Pobierz”)
        if (count($recordingNames) === 1) {
            try {
                $binary = $this->downloadDecryptedUnzipped($recordingNames);
                $out = $targetDirectory . DIRECTORY_SEPARATOR . $recordingNames[0] . '.mp3';
                // jeśli serwer nadal zwrócił ZIP — rozpakuj
                if (str_starts_with($binary, 'PK')) {
                    return $this->extractZipToDirectory($binary, $targetDirectory);
                }
                if (file_put_contents($out, $binary) === false) {
                    throw new WcoException('Nie zapisano MP3: ' . $out);
                }

                return [$out];
            } catch (WcoException) {
                // fallback poniżej
            }
        }

        $zipBinary = $this->downloadRecordingsZip($recordingNames, false);

        return $this->extractZipToDirectory($zipBinary, $targetDirectory);
    }

    /**
     * @return list<string>
     */
    private function extractZipToDirectory(string $zipBinary, string $targetDirectory): array
    {
        $zipPath = $targetDirectory . DIRECTORY_SEPARATOR . 'wco-recordings-' . bin2hex(random_bytes(4)) . '.zip';
        if (file_put_contents($zipPath, $zipBinary) === false) {
            throw new WcoException('Nie zapisano ZIP: ' . $zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new WcoException('Nie można otworzyć ZIP z nagraniami');
        }

        $saved = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }
            $base = basename($name);
            $out = $targetDirectory . DIRECTORY_SEPARATOR . $base;
            $stream = $zip->getStream($name);
            if ($stream === false) {
                continue;
            }
            $data = stream_get_contents($stream);
            fclose($stream);
            if ($data === false) {
                continue;
            }
            file_put_contents($out, $data);
            $saved[] = $out;
        }
        $zip->close();
        @unlink($zipPath);

        return $saved;
    }

    /** @return list<array<string, mixed>> */
    public function getUsers(): array
    {
        $data = $this->jsonGet('/users/');

        return array_is_list($data) ? $data : [];
    }

    /**
     * Lista administratorów VPBX.
     *
     * @return list<array<string, mixed>>
     */
    public function getAdministrators(bool $withCertificates = true): array
    {
        $data = $this->jsonGet('/administrators/', [
            'withCertificates' => $withCertificates ? 'true' : 'false',
        ]);

        return array_is_list($data) ? $data : [];
    }

    /**
     * Role możliwe do przypisania dodatkowym administratorom.
     *
     * @return list<array<string, mixed>>
     */
    public function getAdministratorConfigurableRoles(): array
    {
        $data = $this->jsonGet('/administrators/configurableRoles/');

        return array_is_list($data) ? $data : [];
    }

    public function hasExternalRecordingKeys(): bool
    {
        $response = $this->request('GET', '/administrators/hasExternalRecordingKeys');
        $decoded = json_decode($response['body'], true);

        return $decoded === true;
    }

    /**
     * Tworzy dodatkowego administratora. Odpowiedź to pełna lista administratorów (jak w GUI).
     *
     * Wymagane pola (z HAR): email, login, firstName, lastName, password, roles[],
     * recordingPassword (aktualne hasło nagrań VPBX), recordingKeysPassword, version=-1.
     *
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    public function createAdministrator(array $payload): array
    {
        $data = $this->postJson('/administrators/', $payload);

        return is_array($data) && array_is_list($data) ? $data : (is_array($data) ? [$data] : []);
    }

    /** @return array<string, mixed> */
    public function getMainNumber(): array
    {
        return $this->jsonGet('/functionalities/main-number/');
    }

    /** @return list<array<string, mixed>> */
    public function getAdditionalNumbers(): array
    {
        $data = $this->jsonGet('/functionalities/additional-number/');

        return array_is_list($data) ? $data : [];
    }

    /** @return array<string, mixed> */
    public function getRecordingConfig(): array
    {
        return $this->jsonGet('/functionalities/recording-config');
    }

    /** @return array<string, mixed> */
    public function getApplicationVersion(): array
    {
        return $this->jsonGet('/footer/application-version');
    }

    /** @return array<string, mixed> */
    public function getWizard(): array
    {
        return $this->jsonGet('/wizard');
    }

    /** @return list<mixed>|array<string, mixed> */
    public function getAdministrationInformation(): array
    {
        return $this->jsonGet('/administration-information');
    }

    /** @return array<string, mixed> */
    public function getScenario(): array
    {
        return $this->jsonGet('/graph/scenario');
    }

    /** @return array<string, mixed> */
    public function getStorePackages(): array
    {
        return $this->jsonGet('/store/packages');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAllBlocks(bool $isLite = false): array
    {
        $data = $this->jsonGet('/schema/list-all-blocks', ['isLite' => $isLite ? 'true' : 'false']);

        return array_is_list($data) ? $data : [];
    }

    /** @return list<array<string, mixed>> */
    public function listAllBlocksSettings(): array
    {
        $data = $this->jsonGet('/schema/list-all-blocks-settings');

        return array_is_list($data) ? $data : [];
    }

    /** @return list<array<string, mixed>> */
    public function listFunctionalitiesAll(): array
    {
        $data = $this->jsonGet('/schema/list-functionalities-all');

        return array_is_list($data) ? $data : [];
    }

    /** @return array<string, mixed> */
    public function getRecordingFilterOptions(string $vrType = 'NWI'): array
    {
        return $this->jsonGet('/recordings/filter-options', ['vrType' => $vrType]);
    }

    public function isDeleteRecordingsAllowed(): bool
    {
        $response = $this->request('GET', '/recordings/delete-recordings-allowed');
        $decoded = json_decode($response['body'], true);

        return $decoded === true;
    }

    /**
     * Usuwa nagrania po polu `id` z listy (nie po `name`).
     * GUI: POST /recordings/delete/ z body JSON [id, …].
     *
     * @param list<int|string> $recordingIds
     */
    public function deleteRecordings(array $recordingIds): void
    {
        if ($recordingIds === []) {
            throw new WcoException('Podaj co najmniej jedno ID nagrania');
        }

        $ids = [];
        foreach ($recordingIds as $id) {
            if (!is_numeric($id)) {
                throw new WcoException('ID nagrania musi być liczbą: ' . (string) $id);
            }
            $ids[] = (int) $id;
        }

        $this->request('POST', '/recordings/delete/', [
            'json' => array_values($ids),
            'csrf' => true,
        ]);
    }

    /** @return array<string, mixed> */
    public function isAuthorizedToOpenMessage(): array
    {
        return $this->jsonGet('/user-session-info/isAuthorizedToOpenMessage');
    }

    /**
     * Generyczny GET zwracający JSON.
     *
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|list<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->jsonGet($path, $query);
    }

    /**
     * Generyczny POST JSON.
     *
     * @return array<string, mixed>|list<mixed>|string
     */
    public function postJson(string $path, mixed $json = null): array|string
    {
        $response = $this->request('POST', $path, [
            'json' => $json ?? new \stdClass(),
            'csrf' => true,
        ]);
        $decoded = json_decode($response['body'], true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return $response['body'];
    }

    public function cookies(): CookieJar
    {
        return $this->cookies;
    }

    /**
     * @param array{
     *     query?: array<string, scalar|null>,
     *     json?: mixed,
     *     csrf?: bool,
     *     raw?: bool
     * } $options
     * @return array{status: int, body: string, headers: array<string, list<string>>}
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($options['query'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($options['query']);
        }

        $headers = [
            'Accept: application/json, text/plain, */*',
            'User-Agent: WcoApi/1.0',
        ];

        $body = null;
        if (array_key_exists('json', $options)) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($options['json'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (!empty($options['csrf'])) {
            $xsrf = $this->cookies->get('XSRF-TOKEN');
            if ($xsrf !== null && $xsrf !== '') {
                $headers[] = 'X-XSRF-TOKEN: ' . $xsrf;
            }
        }

        $cookieHeader = $this->cookies->headerValue();
        if ($cookieHeader !== '') {
            $headers[] = 'Cookie: ' . $cookieHeader;
        }

        $headerLines = [];
        $ch = curl_init($url);
        if ($ch === false) {
            throw new WcoException('curl_init failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headerLines): int {
                $len = strlen($line);
                $trimmed = trim($line);
                if ($trimmed !== '' && !str_starts_with(strtolower($trimmed), 'http/')) {
                    $headerLines[] = $trimmed;
                }

                return $len;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($ch);
        if ($rawBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new WcoException('cURL error: ' . $err);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $parsed = $this->parseHeaderLines($headerLines);
        if (isset($parsed['set-cookie'])) {
            $this->cookies->absorbSetCookieHeaders($parsed['set-cookie']);
        }

        if ($status >= 400) {
            throw new WcoException(
                sprintf('HTTP %d dla %s %s: %s', $status, $method, $path, substr((string) $rawBody, 0, 300)),
                $status
            );
        }

        return [
            'status' => $status,
            'body' => (string) $rawBody,
            'headers' => $parsed,
        ];
    }

    /**
     * GUI wysyła archivedBy jako int: 0=NOT_ARCHIVED, 1=BY_SFTP, 2=BY_WEBSERVICE, 3=BY_WWW.
     */
    private function normalizeArchivedBy(string|int $value): int
    {
        if (is_int($value) || ctype_digit((string) $value)) {
            return (int) $value;
        }

        return match (strtoupper((string) $value)) {
            'NOT_ARCHIVED' => 0,
            'BY_SFTP' => 1,
            'BY_WEBSERVICE' => 2,
            'BY_WWW' => 3,
            default => throw new WcoException('Nieznany archivedBy: ' . $value),
        };
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|list<mixed>
     */
    private function jsonGet(string $path, array $query = []): array
    {
        $response = $this->request('GET', $path, $query === [] ? [] : ['query' => $query]);
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new WcoException('Oczekiwano JSON array/object dla ' . $path);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function decodeLoginBody(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        $json = $body;
        $decoded = base64_decode($body, true);
        if ($decoded !== false && str_starts_with(ltrim($decoded), '{')) {
            $json = $decoded;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['_raw' => $body];
        }

        return $data;
    }

    /**
     * @param list<string> $lines
     * @return array<string, list<string>>
     */
    private function parseHeaderLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $out[$name][] = $value;
        }

        return $out;
    }
}
