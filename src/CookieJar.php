<?php

declare(strict_types=1);

namespace WcoApi;

final class CookieJar
{
    private array $cookies = [];

    public function set(string $name, string $value): void
    {
        $this->cookies[$name] = $value;
    }

    public function get(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function absorbSetCookieHeaders(array $setCookieHeaders): void
    {
        foreach ($setCookieHeaders as $header) {
            $part = explode(';', $header, 2)[0];
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $name = trim(substr($part, 0, $eq));
            $value = substr($part, $eq + 1);
            if ($name !== '') {
                $this->cookies[$name] = $value;
            }
        }
    }

    public function headerValue(): string
    {
        $parts = [];
        foreach ($this->cookies as $name => $value) {
            $parts[] = $name . '=' . $value;
        }

        return implode('; ', $parts);
    }

    public function all(): array
    {
        return $this->cookies;
    }
}
