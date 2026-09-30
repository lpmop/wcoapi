# Instalacja

W `composer.json` hosta:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/lpmop/wcoapi.git"
    }
  ],
  "require": {
    "wco/wco-api": "^0.1"
  }
}
```

```bash
composer update wco/wco-api
php bin/console wco:db-install --force
php bin/console wco:whoami
```

Zmienne `WCO_*` w `.env.local`. Opcjonalnie: `examples/wco_api.yaml`, `examples/wco-profiles.local.json.example`.
