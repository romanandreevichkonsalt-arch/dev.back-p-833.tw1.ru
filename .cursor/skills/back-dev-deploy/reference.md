# Deploy reference

## Пути на сервере (FastPanel)

```
/var/www/dev_back_p_8_usr/data/
├── repo/dev.back-p-833.tw1.ru/   ← rsync с локальной машины / CI
├── app/dev.back-p-833.tw1.ru/    ← рабочая копия Yii2
└── www/dev.back-p-833.tw1.ru → symlink на app/.../web
```

## Цепочка режима `files`

1. Локальный `rsync` → `repo/`
2. На сервере `rsync repo/` → `app/`
3. Symlink www, prod env в `index.php` / `yii`, права на `runtime/`

Без composer, migrate, open-api.

## Узкое место `full`

`php yii open-api/generate` сканирует `docs/` + `controllers/`, swagger-php ~4 мин локально, 15–20 мин на dev VPS.

Оптимизация (не реализована): пропуск при `OpenApiGeneratorService::isRuntimeFresh()`.

## Настройка SSH-ключа

На сервере один раз:

```bash
bash deploy/setup-github-actions-key.sh
```

Локально скопировать приватный ключ или использовать тот же для `SSH_IDENTITY_FILE`.
