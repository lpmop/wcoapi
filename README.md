# wco/wco-api

Symfony Bundle + CLI dla panelu WCO Orange (`admin_api`).

Wymagania: PHP ≥ 8.2, `ext-curl`, `ext-json`, `ext-zip`, Symfony 7/8, Doctrine ORM 3.

## Instalacja (Composer + GitHub)

W `composer.json` projektu hosta:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ORG/wco-api.git"
    }
  ],
  "require": {
    "wco/wco-api": "^0.1"
  }
}
```

```bash
composer update wco/wco-api
```

Dla prywatnego repo użyj SSH (`git@github.com:ORG/wco-api.git`) albo [Composer GitHub token](https://getcomposer.org/doc/articles/authentication-for-private-packages.md).

Flex zwykle dopisze bundle; inaczej w `config/bundles.php`:

```php
WcoApi\WcoApiBundle::class => ['all' => true],
```

### Instalacja path (development)

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../wco-api",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "wco/wco-api": "*"
  }
}
```

## Konfiguracja

Env (`.env` / `.env.local`):

```
WCO_BASE_URL=https://wco.orange.pl/admin_api
WCO_VPBX=
WCO_USER=
WCO_PASS=
WCO_REC_PASS=
WCO_SSL_VERIFY=0
WCO_STORAGE_DRIVER=local
WCO_STORAGE_PATH=var/recordings
WCO_DB_TRACK_DOWNLOADS=1
WCO_PROFILE=
WCO_PROFILES_FILE=
```

Opcjonalnie skopiuj `examples/wco_api.yaml` → `config/packages/wco_api.yaml`.

Profile kont: skopiuj `examples/wco-profiles.local.json.example` → `config/wco-profiles.local.json` (plik lokalny, nie commitować haseł).

### Baza (tracking pobrań)

```bash
php bin/console wco:db-install
php bin/console wco:db-install --force
php bin/console wco:db-install --migration
php bin/console doctrine:migrations:migrate
```

### CLI

```bash
php bin/console list wco
php bin/console wco:calls
php bin/console wco:whoami -p NAZWA_PROFILU
```

## Standalone (bez hosta Symfony)

Po `composer require wco/wco-api` (+ `symfony/dotenv`):

```bash
php vendor/bin/wco wco:whoami
```

Wymaga `.env` w katalogu projektu z `WCO_*`.

## Wersjonowanie

Tagi Git (`v0.1.0`, `v0.2.0`, …) — Composer czyta wersję z tagów, nie z `composer.json`.

## Licencja

Proprietary — zobacz [LICENSE](LICENSE).
