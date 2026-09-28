<?php

declare(strict_types=1);

namespace WcoApi;

/**
 * Buduje {@see WcoProfiles} ze ścieżki względem projectDir / env.
 */
final class WcoProfilesFactory
{
    public static function create(string $projectDir, string $fileFromEnv, string $envDefaultProfile): WcoProfiles
    {
        $path = trim($fileFromEnv);
        if ($path === '') {
            $path = $projectDir . '/config/wco-profiles.local.json';
        } elseif (!preg_match('#^(?:[a-zA-Z]:)?[/\\\\]#', $path)) {
            $path = $projectDir . '/' . ltrim(str_replace('\\', '/', $path), '/');
        }

        return new WcoProfiles($path, trim($envDefaultProfile));
    }
}
