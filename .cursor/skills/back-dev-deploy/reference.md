# Deploy reference

## Staging — api.dev-fabrika.ru

```
/var/www/api_dev_fabr_usr/data/
├── repo/api.dev-fabrika.ru/
├── app/api.dev-fabrika.ru/
└── www/api.dev-fabrika.ru → app/.../web
```

## Production — api.mf-anna.ru

```
/var/www/api_mf_anna__usr/data/
├── repo/api.mf-anna.ru/
├── app/api.mf-anna.ru/
└── www/api.mf-anna.ru → app/.../web
```

## Цепочка режима `files`

1. Локальный `rsync` → `repo/`
2. На сервере `rsync repo/` → `app/`
3. Symlink www, prod env в `index.php` / `yii`, права на `runtime/`

`web/uploads/` и `.env` не перезаписываются деплоем.

## Legacy

`dev.back-p-833.tw1.ru` · `./deploy/deploy.sh` без обёртки · [AGENT-DEPLOY.md](../../../deploy/AGENT-DEPLOY.md)
