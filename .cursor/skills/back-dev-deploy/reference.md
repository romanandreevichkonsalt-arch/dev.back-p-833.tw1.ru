# Deploy reference — api.dev-fabrika.ru

## Пути на сервере (FastPanel)

```
/var/www/api_dev_fabr_usr/data/
├── repo/api.dev-fabrika.ru/   ← rsync с локальной машины / CI
├── app/api.dev-fabrika.ru/    ← рабочая копия Yii2
└── www/api.dev-fabrika.ru → symlink на app/.../web
```

## Цепочка режима `files`

1. Локальный `rsync` → `repo/`
2. На сервере `rsync repo/` → `app/`
3. Symlink www, prod env в `index.php` / `yii`, права на `runtime/`

Без composer, migrate, open-api.

## Узкое место `full`

`php yii open-api/generate` — долго на VPS; используй `full` только при изменении OpenAPI.

## Legacy (не по умолчанию)

`dev.back-p-833.tw1.ru` · `dev_back_p_8_usr@200.165.224.88` · `./deploy/deploy.sh` без prod-обёртки.
