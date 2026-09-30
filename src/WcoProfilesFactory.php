<?php

declare(strict_types=1);

namespace WcoApi;

final class WcoProfilesFactory
{
    public static function create(string $projectDir, string $fileFromEnv, string $envDefaultProfile): WcoProfiles
    {
        $path = trim($fileFromEnv);
        if ($path === '') {
            $path = $projectDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'wco-profiles.local.json';
        } elseif (!preg_match('#^(?:[a-zA-Z]:)?[/\\\\]#', $path)) {
            $path = $projectDir . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        }

        return new WcoProfiles($path, trim($envDefaultProfile));
    }
}
