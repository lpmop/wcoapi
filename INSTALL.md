# Instalacja w projekcie Symfony

## Z GitHub (zalecane)

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/ORG/wco-api.git"
  }
],
"require": {
  "wco/wco-api": "^0.1"
}
```

```bash
composer update wco/wco-api
# upewnij się, że WcoApi\WcoApiBundle jest w config/bundles.php
php bin/console wco:db-install --force
php bin/console wco:whoami
```

Zamień `ORG/wco-api` na właściwy owner/repo.

## Path (lokalny development)

```json
"repositories": [
  { "type": "path", "url": "../wco-api", "options": { "symlink": true } }
],
"require": {
  "wco/wco-api": "*"
}
```

## Konfiguracja

1. Zmienne `WCO_*` w `.env.local` (lista w README).
2. Opcjonalnie: `examples/wco_api.yaml` → `config/packages/wco_api.yaml`.
3. Profile: `examples/wco-profiles.local.json.example` → `config/wco-profiles.local.json`.
